<?php
include('templates/header_admin.php');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$host='localhost'; $user='root'; $pass=''; $db='airlines';
$conn = new mysqli($host,$user,$pass,$db);
$quizzes = []; $dbError = null; $totalStudents=0; $totalCreated=0;

if ($conn->connect_error) { $dbError = $conn->connect_error; }
else {
    $isAdminSignedIn = !empty($_SESSION['acc_id']) && !empty($_SESSION['acc_role']) && $_SESSION['acc_role'] === 'admin';
    if ($isAdminSignedIn) {
        $myAcc = $_SESSION['acc_id'];
        $cols=[]; $cRes=$conn->query("SHOW COLUMNS FROM `quizzes`");
        if ($cRes) { while($c=$cRes->fetch_assoc()) $cols[]=$c['Field']; $cRes->free(); }
        $candidates = ['created_by','creator','author','acc_id','account_id','admin_id','created_by_id','owner_id','user_id'];
        $creator = null;
        foreach ($candidates as $cand) if (in_array($cand,$cols,true)) { $creator=$cand; break; }
        if (!$creator) foreach ($cols as $col) if (preg_match('/\b(created|creator|author|owner|admin|user|acc)\b/i',$col)) { $creator=$col; break; }

        if ($creator) {
            $creatorQuoted = "`".str_replace("`","``",$creator)."`";
            $sql = "SELECT id,title,code,COALESCE(deadline,'') AS deadline,COALESCE(num_questions,0) AS num_questions FROM quizzes WHERE $creatorQuoted = ? ORDER BY id DESC";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('s',$myAcc);
                $stmt->execute();
                $res = $stmt->get_result();
                while($r=$res->fetch_assoc()) $quizzes[]=$r;
                if ($res) $res->free();
                $stmt->close();
            } else {
                $dbError = 'Failed to prepare creator-filter query; showing all quizzes.';
            }
            $r = $conn->query("SELECT COUNT(*) AS c FROM students"); if ($r){ $row=$r->fetch_assoc(); $totalStudents=(int)$row['c']; $r->free(); }
            $stmt2 = $conn->prepare("SELECT COUNT(*) AS c FROM quizzes WHERE $creatorQuoted = ?");
            if ($stmt2) { $stmt2->bind_param('s',$myAcc); $stmt2->execute(); $res2=$stmt2->get_result(); if ($res2 && $row2=$res2->fetch_assoc()) $totalCreated=(int)$row2['c']; if ($res2) $res2->free(); $stmt2->close(); }
        } else {
            $dbError = 'Creator column not detected in quizzes table; showing all quizzes.';
            $r = $conn->query("SELECT id,title,code,COALESCE(deadline,'') AS deadline,COALESCE(num_questions,0) AS num_questions FROM quizzes ORDER BY id DESC");
            if ($r) { while($row=$r->fetch_assoc()) $quizzes[]=$row; $r->free(); }
            $r = $conn->query("SELECT COUNT(*) AS c FROM students"); if ($r){ $row=$r->fetch_assoc(); $totalStudents=(int)$row['c']; $r->free(); }
            $r = $conn->query("SELECT COUNT(*) AS c FROM quizzes"); if ($r){ $row=$r->fetch_assoc(); $totalCreated=(int)$row['c']; $r->free(); }
        }
    } else {
        $r = $conn->query("SELECT id,title,code,COALESCE(deadline,'') AS deadline,COALESCE(num_questions,0) AS num_questions FROM quizzes ORDER BY id DESC");
        if ($r) { while($row=$r->fetch_assoc()) $quizzes[]=$row; $r->free(); }
        $r = $conn->query("SELECT COUNT(*) AS c FROM students"); if ($r){ $row=$r->fetch_assoc(); $totalStudents=(int)$row['c']; $r->free(); }
        $r = $conn->query("SELECT COUNT(*) AS c FROM quizzes"); if ($r){ $row=$r->fetch_assoc(); $totalCreated=(int)$row['c']; $r->free(); }
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — Your Quizzes</title>
<link rel="stylesheet" href="css/admin.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
.frame-card{border-radius:8px;padding:12px;margin-bottom:10px;background:#fff;box-shadow:0 6px 18px rgba(0,0,0,0.04);display:flex;align-items:center;justify-content:space-between}
.quiz-title{font-weight:700}.quiz-dead{color:#666;font-size:13px}.left-create{display:inline-block;padding:8px 12px;background:#0d6efd;color:#fff;border-radius:8px;text-decoration:none}.edit-circle{cursor:pointer;padding:8px}
.open-btn { text-decoration:none; color:#0b5ed7; padding:6px 8px; border-radius:6px; border:1px solid transparent; }
.open-btn:hover { background:#f1f4ff; }
</style>
</head><body>
<div class="page-wrap">
  <div class="layout container" style="display:grid;grid-template-columns:200px 1fr 320px;gap:18px;align-items:start">
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
        </div>
        <canvas id="quizPieChart" style="width:100%;height:120px;margin-top:12px;"></canvas>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<script>
(function(){
  const totalStudents = <?php echo (int)$totalStudents; ?>;
  const totalCreated = <?php echo (int)$totalCreated; ?>;
  const ctxEl = document.getElementById('quizPieChart');
  if (ctxEl) {
    const ctx = ctxEl.getContext('2d');
    const remaining = Math.max(0, totalStudents - totalCreated);
    new Chart(ctx,{type:'pie',data:{labels:['Your quizzes','Remaining'],datasets:[{data:[totalCreated,remaining]}]},options:{plugins:{legend:{display:false}}}});
  }

  // make card clickable to open the report page as well
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
</body></html>
