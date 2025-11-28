<?php
// admin_quizzes.php — robust, auto-detecting column names (paste to server)

include('templates/header_admin.php');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$host='localhost'; $user='root'; $pass=''; $db='airlines';
$conn = new mysqli($host,$user,$pass,$db);

$quizzes = [];
$dbError = null;
$totalStudents = 0;
$totalCreated = 0;
$teacherStudents = 0;
$quizStats = [];

if ($conn->connect_error) {
    $dbError = $conn->connect_error;
} else {
    // Detect admin signed in (your app used 'admin' as teacher role)
    $isAdminSignedIn = !empty($_SESSION['acc_id']) && !empty($_SESSION['acc_role']) && $_SESSION['acc_role'] === 'admin';
    if ($isAdminSignedIn) {
        $myAcc = $_SESSION['acc_id'];

        // detect creator column in quizzes table
        $cols = [];
        $cRes = $conn->query("SHOW COLUMNS FROM `quizzes`");
        if ($cRes) {
            while ($c = $cRes->fetch_assoc()) $cols[] = $c['Field'];
            $cRes->free();
        }
        $candidates = ['created_by','creator','author','acc_id','account_id','admin_id','created_by_id','owner_id','user_id'];
        $creator = null;
        foreach ($candidates as $cand) if (in_array($cand, $cols, true)) { $creator = $cand; break; }
        if (!$creator) {
            foreach ($cols as $col) {
                if (preg_match('/\b(created|creator|author|owner|admin|user|acc)\b/i',$col)) { $creator = $col; break; }
            }
        }

        if ($creator) {
            $creatorQuoted = "`".str_replace("`","``",$creator)."`";
            $sql = "SELECT id,title,code,COALESCE(deadline,'') AS deadline,COALESCE(num_questions,0) AS num_questions FROM quizzes WHERE $creatorQuoted = ? ORDER BY id DESC";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('s', $myAcc);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) $quizzes[] = $r;
                if ($res) $res->free();
                $stmt->close();
            } else {
                $dbError = 'Failed to prepare creator-filter query; showing all quizzes.';
            }

            // total students overall
            $r = $conn->query("SELECT COUNT(*) AS c FROM students");
            if ($r) { $row = $r->fetch_assoc(); $totalStudents = (int)$row['c']; $r->free(); }

            // students assigned to this teacher (teacher_id is expected but we detect fallback)
            // detect students table columns
            $studentCols = [];
            $sRes = $conn->query("SHOW COLUMNS FROM `students`");
            if ($sRes) { while($sc=$sRes->fetch_assoc()) $studentCols[]=$sc['Field']; $sRes->free(); }
            $studentIdCol = null;
            $teacherIdCol = null;
            // student id column in students: prefer 'student_id' else 'acc_id' else 'id' as fallback
            foreach (['student_id','acc_id','user_id','account_id','sid','id'] as $cname) if (in_array($cname,$studentCols,true)) { $studentIdCol = $cname; break; }
            // teacher column: prefer 'teacher_id' else 'assigned_teacher' else 'admin_id'
            foreach (['teacher_id','assigned_teacher','admin_id','owner_id'] as $cname) if (in_array($cname,$studentCols,true)) { $teacherIdCol = $cname; break; }
            if (!$teacherIdCol) $teacherIdCol = 'teacher_id'; // leave as expected; queries that use it will fail and we handle that later

            // count teacher students
            if ($teacherIdCol && $studentIdCol) {
                $stmtTs = $conn->prepare("SELECT COUNT(*) AS c FROM students WHERE `{$teacherIdCol}` = ?");
                if ($stmtTs) {
                    $stmtTs->bind_param('s', $myAcc);
                    $stmtTs->execute();
                    $rts = $stmtTs->get_result();
                    if ($rts && $rowts = $rts->fetch_assoc()) $teacherStudents = (int)$rowts['c'];
                    if ($rts) $rts->free();
                    $stmtTs->close();
                } else {
                    // fallback
                    $esc = $conn->real_escape_string($myAcc);
                    $r2 = $conn->query("SELECT COUNT(*) AS c FROM students WHERE `{$teacherIdCol}` = '{$esc}'");
                    if ($r2) { $row2 = $r2->fetch_assoc(); $teacherStudents = (int)$row2['c']; $r2->free(); }
                }
            } else {
                // couldn't detect student/teacher columns — leave teacherStudents = 0
            }

            // how many quizzes the admin created
            $stmt2 = $conn->prepare("SELECT COUNT(*) AS c FROM quizzes WHERE $creatorQuoted = ?");
            if ($stmt2) {
                $stmt2->bind_param('s', $myAcc);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                if ($res2 && $row2 = $res2->fetch_assoc()) $totalCreated = (int)$row2['c'];
                if ($res2) $res2->free();
                $stmt2->close();
            }

            //
            // === Build per-quiz submission stats robustly ===
            //
            // Detect submitted_flights columns for quiz id and account/student id used in submissions.
            $sfCols = [];
            $sfRes = $conn->query("SHOW COLUMNS FROM `submitted_flights`");
            if ($sfRes) { while($sc=$sfRes->fetch_assoc()) $sfCols[]=$sc['Field']; $sfRes->free(); }

            // candidates for quiz id column
            $quizCol = null;
            foreach (['quiz_id','quizid','quiz','exam_id','test_id'] as $cname) if (in_array($cname,$sfCols,true)) { $quizCol = $cname; break; }
            // candidates for acc/student column in submissions
            $accCol = null;
            foreach (['acc_id','student_id','user_id','account_id','submitted_by','submitted_acc'] as $cname) if (in_array($cname,$sfCols,true)) { $accCol = $cname; break; }

            // If we didn't detect the student id column in submitted_flights, try to detect 'sid' or 'id'
            if (!$accCol) {
                foreach (['sid','id'] as $cname) if (in_array($cname,$sfCols,true)) { $accCol = $cname; break; }
            }

            // If detection failed, fall back to older assumption and set error notice
            if (!$quizCol || !$accCol || !$studentIdCol) {
                // We will not abort; produce quizStats with fallback counts (all submissions per quiz), but flag dbError
                $dbError = ($dbError ? $dbError . ' ' : '') . 'Could not auto-detect some columns in submitted_flights/students; per-quiz stats may be approximated.';
            }

            // For each quiz, count distinct submitters among this teacher's students
            foreach ($quizzes as $q) {
                $qid = (int)$q['id'];
                $submitted = 0;

                // prefer JOIN approach when all column names are detected
                if ($quizCol && $accCol && $studentIdCol && $teacherIdCol) {
                    // Build query with safe backticks
                    $qq = "SELECT COUNT(DISTINCT sf.`" . str_replace("`","``",$accCol) . "`) AS c
                           FROM `submitted_flights` sf
                           JOIN `students` s ON sf.`" . str_replace("`","``",$accCol) . "` = s.`" . str_replace("`","``",$studentIdCol) . "`
                           WHERE sf.`" . str_replace("`","``",$quizCol) . "` = ? AND s.`" . str_replace("`","``",$teacherIdCol) . "` = ?";

                    $stmtS = $conn->prepare($qq);
                    if ($stmtS) {
                        $stmtS->bind_param("is", $qid, $myAcc);
                        $stmtS->execute();
                        $rS = $stmtS->get_result();
                        $submitted = ($rS && $rowS = $rS->fetch_assoc()) ? (int)$rowS['c'] : 0;
                        if ($rS) $rS->free();
                        $stmtS->close();
                    } else {
                        // fallback to simpler query if prepare fails
                        $escqid = $conn->real_escape_string($qid);
                        $rsc = $conn->query("SELECT COUNT(DISTINCT `{$accCol}`) AS c FROM submitted_flights WHERE `{$quizCol}` = {$escqid}");
                        $submitted = ($rsc && $rowc = $rsc->fetch_assoc()) ? (int)$rowc['c'] : 0;
                        if ($rsc) $rsc->free();
                    }
                } else {
                    // fallback: count distinct acc/users for the quiz across all submissions (not teacher-scoped)
                    if ($quizCol) {
                        $escqid = $conn->real_escape_string($qid);
                        $rsc = $conn->query("SELECT COUNT(DISTINCT `" . ($accCol ?: 'acc_id') . "`) AS c FROM submitted_flights WHERE `" . ($quizCol ?: 'quiz_id') . "` = {$escqid}");
                        $submitted = ($rsc && $rowc = $rsc->fetch_assoc()) ? (int)$rowc['c'] : 0;
                        if ($rsc) $rsc->free();
                    } else {
                        // no detection at all — set submitted=0
                        $submitted = 0;
                    }
                }

                $notSubmitted = max(0, $teacherStudents - $submitted);
                $quizStats[] = [
                    'id' => $qid,
                    'title' => $q['title'],
                    'code' => $q['code'],
                    'submitted' => $submitted,
                    'not_submitted' => $notSubmitted
                ];
            }

        } else {
            $dbError = 'Creator column not detected in quizzes table; showing all quizzes.';
            $r = $conn->query("SELECT id,title,code,COALESCE(deadline,'') AS deadline,COALESCE(num_questions,0) AS num_questions FROM quizzes ORDER BY id DESC");
            if ($r) { while ($row = $r->fetch_assoc()) $quizzes[] = $row; $r->free(); }
            $r = $conn->query("SELECT COUNT(*) AS c FROM students"); if ($r){ $row = $r->fetch_assoc(); $totalStudents = (int)$row['c']; $r->free(); }
            $r = $conn->query("SELECT COUNT(*) AS c FROM quizzes"); if ($r){ $row = $r->fetch_assoc(); $totalCreated = (int)$row['c']; $r->free(); }
            // no per-quiz stats when detection fails
        }

    } else {
        // not signed-in admin: show all quizzes (read-only view)
        $r = $conn->query("SELECT id,title,code,COALESCE(deadline,'') AS deadline,COALESCE(num_questions,0) AS num_questions FROM quizzes ORDER BY id DESC");
        if ($r) { while ($row = $r->fetch_assoc()) $quizzes[] = $row; $r->free(); }
        $r = $conn->query("SELECT COUNT(*) AS c FROM students"); if ($r){ $row = $r->fetch_assoc(); $totalStudents = (int)$row['c']; $r->free(); }
        $r = $conn->query("SELECT COUNT(*) AS c FROM quizzes"); if ($r){ $row = $r->fetch_assoc(); $totalCreated = (int)$row['c']; $r->free(); }
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — Your Quizzes</title>

<link rel="stylesheet" href="css/admin.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glider-js@1/glider.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* styles omitted for brevity — reuse your existing styles */
.frame-card{border-radius:8px;padding:12px;margin-bottom:10px;background:#fff;box-shadow:0 6px 18px rgba(0,0,0,0.04);display:flex;align-items:center;justify-content:space-between;transition: transform .18s ease}
.frame-card:hover{transform:translateY(-4px)}
.quiz-title{font-weight:700}
.quiz-dead{color:#666;font-size:13px}
.left-create{display:inline-block;padding:8px 12px;background:#0d6efd;color:#fff;border-radius:8px;text-decoration:none}
.pie-canvas{width:100% !important;height:160px !important;max-width:260px;margin:0 auto;display:block}
</style>
</head>
<body>
<div class="page-wrap">
  <div class="layout container" style="display:grid;grid-template-columns:200px 1fr 360px;gap:18px;align-items:start">
    <div class="left-col"><a class="left-create" href="quizmaker.php">CREATE QUIZ</a></div>
    <div>
      <div class="quiz-list"><div id="quizzesContainer">
        <?php if(!empty($dbError)): ?><div class="frame-card">Notice: <?php echo htmlspecialchars($dbError); ?></div><?php endif; ?>
        <?php if(count($quizzes)===0): ?>
          <?php if(!empty($_SESSION['acc_id']) && !empty($_SESSION['acc_role']) && $_SESSION['acc_role']==='admin'): ?>
            <div class="frame-card">You haven't created any quizzes yet.</div>
          <?php else: ?>
            <div class="frame-card">No quizzes found. Click CREATE QUIZ to add one.</div>
          <?php endif; ?>
        <?php else: foreach($quizzes as $q): ?>
          <div class="frame-card" data-id="<?php echo (int)$q['id']; ?>">
            <div style="flex:1;display:flex;gap:12px;align-items:center;">
              <div style="width:48px;height:48px;border-radius:6px;background:#eef6ff;display:flex;align-items:center;justify-content:center;font-weight:800;color:#0b5ed7;"><?php echo htmlspecialchars($q['code']?:'Q'); ?></div>
              <div style="flex:1">
                <div class="quiz-title"><?php echo htmlspecialchars($q['title']); ?></div>
                <div class="quiz-dead">DEADLINE: <?php echo $q['deadline']?htmlspecialchars($q['deadline']):'—'; ?></div>
              </div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;margin-left:12px;">
              <a class="open-btn" href="quiz_report.php?id=<?php echo (int)$q['id']; ?>">Open Report</a>
              <a class="edit-circle" href="quizmaker.php?id=<?php echo (int)$q['id']; ?>" title="Edit"><i class="material-icons">edit</i></a>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div></div>
    </div>

    <div>
      <?php if(!empty($_SESSION['acc_id']) && !empty($_SESSION['acc_role']) && $_SESSION['acc_role']==='admin'): ?>
        <div style="margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;background:#fff;padding:10px;border-radius:8px;">
          <div style="display:flex;gap:10px;align-items:center;">
            <div style="width:56px;height:56px;border-radius:8px;background:#0b5ed7;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;"><?php echo strtoupper(substr($_SESSION['acc_name'] ?? $_SESSION['acc_id'],0,2)); ?></div>
            <div><div style="font-weight:700;"><?php echo htmlspecialchars($_SESSION['acc_name'] ?? $_SESSION['acc_id']); ?></div><div style="font-size:13px;color:#666;"><?php echo htmlspecialchars($_SESSION['acc_id']); ?> • <?php echo htmlspecialchars($_SESSION['acc_role']); ?></div></div>
          </div>
          <div><a href="Ad_out.php" style="text-decoration:none;padding:8px 10px;border-radius:6px;background:#f8f9fa;color:#333;border:1px solid #ddd;">Sign out</a></div>
        </div>
      <?php else: ?>
        <div style="margin-bottom:12px;text-align:center;background:#fff;padding:12px;border-radius:8px;"><div style="font-weight:700;color:#333;">Not signed in</div><div style="font-size:13px;color:#666;">Please log in to see admin actions.</div></div>
      <?php endif; ?>

      <div style="display:flex;justify-content:flex-end;margin-bottom:18px;"><a href="Students.php" class="edit-students-btn">EDIT STUDENTS</a></div>

      <div style="background:#fff;padding:12px;border-radius:8px;margin-bottom:12px;">
        <div style="font-weight:700;margin-bottom:8px;">Summary</div>
        <div style="display:flex;gap:12px;align-items:center;">
          <div><div style="font-size:12px;color:#666;">Total students</div><div style="font-weight:700;font-size:18px;"><?php echo (int)$totalStudents; ?></div></div>
          <div><div style="font-size:12px;color:#666;">Quizzes you created</div><div style="font-weight:700;font-size:18px;"><?php echo (int)$totalCreated; ?></div></div>
          <div><div style="font-size:12px;color:#666;">Your assigned students</div><div style="font-weight:700;font-size:18px;"><?php echo (int)$teacherStudents; ?></div></div>
        </div>

        <!-- QUIZ PIE CAROUSEL -->
        <div class="glider-contain">
          <div class="glider" id="quizPieCarousel">
            <?php if (empty($quizStats)): ?>
              <div class="slide"><div style="padding:14px;text-align:center;color:#666">No per-quiz stats available.</div></div>
            <?php else: foreach ($quizStats as $qs): ?>
              <div class="slide" style="padding:10px;text-align:center;">
                <h6 style="margin:6px 0 8px;"><?php echo htmlspecialchars($qs['title']); ?></h6>
                <canvas id="pie_<?php echo $qs['id']; ?>" class="pie-canvas"></canvas>
                <div style="font-size:12px; margin-top:8px; color:#444;">
                  Submitted: <b><?php echo $qs['submitted']; ?></b> &nbsp;|&nbsp;
                  Not Submitted: <b><?php echo $qs['not_submitted']; ?></b>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>

          <button class="glider-prev" aria-label="Previous">«</button>
          <button class="glider-next" aria-label="Next">»</button>
          <div id="dots"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/glider-js@1/glider.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>

<script>
(function(){
  const BLUE = '#1976d2';
  const GREY = '#9e9e9e';

  const gliderEl = document.querySelector('#quizPieCarousel');
  var gliderInstance = null;
  if (gliderEl) {
    gliderInstance = new Glider(gliderEl, {
      slidesToShow: 1,
      slidesToScroll: 1,
      draggable: true,
      dots: '#dots',
      arrows: { prev: '.glider-prev', next: '.glider-next' },
      rewind: true
    });
    let autoplayTimer = null;
    function startAutoplay(){ stopAutoplay(); autoplayTimer = setInterval(()=>{ try{ gliderInstance.scrollItem('next'); }catch(e){} }, 4200); }
    function stopAutoplay(){ if (autoplayTimer) { clearInterval(autoplayTimer); autoplayTimer = null; } }
    gliderEl.addEventListener('mouseenter', stopAutoplay);
    gliderEl.addEventListener('mouseleave', startAutoplay);
    startAutoplay();
  }

  <?php foreach ($quizStats as $qs): ?>
    (function(){
      const submitted = <?php echo (int)$qs['submitted']; ?>;
      const notSubmitted = <?php echo (int)$qs['not_submitted']; ?>;
      const ctx = document.getElementById('pie_<?php echo $qs['id']; ?>');
      if (!ctx) return;
      const total = submitted + notSubmitted;
      new Chart(ctx.getContext('2d'), {
        type: 'pie',
        data: {
          labels: ['Submitted','Not Submitted'],
          datasets: [{ data: [submitted, notSubmitted], backgroundColor: [BLUE, GREY], hoverOffset: 8 }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: { animateRotate:true, animateScale:true, duration:800, easing:'easeOutQuart' },
          plugins: {
            legend: { display: true, position: 'bottom' },
            tooltip: {
              enabled: true,
              callbacks: {
                label: function(context) {
                  const label = context.label || '';
                  const value = context.parsed || 0;
                  const percent = total > 0 ? ((value/total)*100).toFixed(1) : '0.0';
                  return label + ': ' + value + ' (' + percent + '%)';
                }
              }
            }
          }
        }
      });
    })();
  <?php endforeach; ?>

  document.querySelectorAll('#quizzesContainer .frame-card').forEach(card=>{
    const id = card.dataset.id;
    if(id){
      card.style.cursor='pointer';
      card.addEventListener('click',(e)=>{
        if(e.target.closest('.edit-circle')||e.target.closest('a')) return;
        window.location.href='quiz_report.php?id='+id;
      });
    }
  });

})();
</script>

<?php include('templates/footer.php'); ?>
</body>
</html>
