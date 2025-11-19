<?php
// admin.php - simplified builder: ONLY airport questions (Country -> City -> Airport)
session_start();
require_once 'db.php';

$ADMIN_PASS = 'adminpass'; // change this!

// ---------- AJAX endpoints ----------
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['ajax'];
    if ($action === 'countries') {
        $res = $mysqli->query("SELECT DISTINCT CountryRegion FROM airports WHERE COALESCE(CountryRegion,'')<>'' ORDER BY CountryRegion");
        $out = [];
        while ($r = $res->fetch_assoc()) $out[] = $r['CountryRegion'];
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'cities') {
        $country = $_GET['country'] ?? '';
        $stmt = $mysqli->prepare("SELECT DISTINCT City FROM airports WHERE COALESCE(CountryRegion,'')<>'' AND UPPER(CountryRegion)=UPPER(?) AND COALESCE(City,'')<>'' ORDER BY City");
        $stmt->bind_param('s', $country);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        while ($r = $res->fetch_assoc()) $out[] = $r['City'];
        $stmt->close();
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'airports') {
        $country = $_GET['country'] ?? '';
        $city = $_GET['city'] ?? '';
        $stmt = $mysqli->prepare("SELECT IATACode, AirportName FROM airports WHERE UPPER(CountryRegion)=UPPER(?) AND UPPER(City)=UPPER(?) AND COALESCE(IATACode,'')<>'' ORDER BY AirportName");
        $stmt->bind_param('ss', $country, $city);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        while ($r = $res->fetch_assoc()) $out[] = $r;
        $stmt->close();
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode([]);
    exit;
}

// ---------- Admin auth ----------
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $pw = $_POST['password'] ?? '';
    if ($pw === $ADMIN_PASS) {
        $_SESSION['is_admin'] = true;
    } else {
        $error = "Invalid password";
    }
}
if (isset($_GET['logout'])) {
    unset($_SESSION['is_admin']);
    header("Location: admin.php"); exit;
}

// ---------- Save quiz + airport questions (batch) ----------
if (!empty($_POST['save_quiz']) && !empty($_SESSION['is_admin'])) {
    $title = trim($_POST['quiz_title'] ?? '');
    $description = trim($_POST['quiz_description'] ?? '');
    $deadline = !empty($_POST['quiz_deadline']) ? trim($_POST['quiz_deadline']) : null;
    $questions_json = $_POST['questions_json'] ?? '';
    $questions = json_decode($questions_json, true);

    if (!$title) {
        $_SESSION['msg'] = "Quiz title required.";
        header("Location: admin.php");
        exit;
    }
    // create quiz
    $stmt = $mysqli->prepare("INSERT INTO quizzes (title,description,deadline) VALUES (?,?,?)");
    $stmt->bind_param('sss', $title, $description, $deadline);
    $stmt->execute();
    $quiz_id = $mysqli->insert_id;
    $stmt->close();

    // insert questions (airport-type expected)
    $ins = $mysqli->prepare("INSERT INTO questions (quiz_id,prompt,question_type,choices,answer,points) VALUES (?,?,?,?,?,?)");
    foreach ($questions as $q) {
        // expected q structure: { type: 'airport', airport_iata:'', answer_type:'iata'|'name', prompt:'', points:1 }
        $question_type = 'text';
        $prompt = $q['prompt'] ?? '';
        $answer = $q['answer'] ?? '';
        $choices = null;
        $points = intval($q['points'] ?? 1);

        if (($q['type'] ?? '') === 'airport') {
            $question_type = 'text';
            $airport_iata = $q['airport_iata'] ?? '';
            $answer_type = $q['answer_type'] ?? 'iata';
            $stm = $mysqli->prepare("SELECT IATACode, AirportName, City, CountryRegion FROM airports WHERE UPPER(IATACode)=UPPER(?) LIMIT 1");
            $stm->bind_param('s', $airport_iata);
            $stm->execute();
            $ap = $stm->get_result()->fetch_assoc();
            $stm->close();
            if ($ap) {
                if ($answer_type === 'iata') {
                    $answer = strtoupper($ap['IATACode']);
                    if (!$prompt) $prompt = "Type the IATA code for " . $ap['AirportName'] . " (" . $ap['City'] . ", " . $ap['CountryRegion'] . ")";
                } else {
                    $answer = $ap['AirportName'];
                    if (!$prompt) $prompt = "Type the airport name for IATA code " . strtoupper($ap['IATACode']) . " (" . $ap['City'] . ", " . $ap['CountryRegion'] . ")";
                }
            }
        } else {
            // ignore non-airport items (defensive)
            continue;
        }

        $ins->bind_param('issssi', $quiz_id, $prompt, $question_type, $choices, $answer, $points);
        $ins->execute();
    }
    $ins->close();

    $_SESSION['msg'] = "Quiz and " . count($questions) . " airport question(s) saved.";
    header("Location: admin.php");
    exit;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin - Airport Quiz Builder</title>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css"/>
  <style>
    .section-title { color:#777; border-bottom:1px solid #eee; padding-bottom:6px; margin-bottom:12px; }
    .qb-card { margin-bottom:12px; }
    .muted { color:#666; font-size:.95rem; }
    .small-btn { padding:0 10px; height:32px; line-height:32px; }
  </style>
</head>
<body>
<nav class="blue">
  <div class="nav-wrapper container">
    <a href="index.php" class="brand-logo">Quiz Maker</a>
    <ul id="nav-mobile" class="right">
      <?php if (!empty($_SESSION['is_admin'])): ?>
        <li><a href="?logout=1">Logout</a></li>
      <?php else: ?>
        <li><a href="index.php">Home</a></li>
      <?php endif; ?>
    </ul>
  </div>
</nav>

<div class="container" style="margin-top:18px;">
<?php if (empty($_SESSION['is_admin'])): ?>
  <h5>Admin Login</h5>
  <?php if (!empty($error)): ?><p class="red-text"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="action" value="login">
    <div class="input-field">
      <input id="password" type="password" name="password" required>
      <label for="password">Admin Password</label>
    </div>
    <button class="btn">Login</button>
  </form>

<?php else: ?>
  <h5>Create Quiz (Airport-only Form Builder)</h5>

  <?php if (!empty($_SESSION['msg'])): ?>
    <div class="card-panel teal lighten-5"><?php echo htmlspecialchars($_SESSION['msg']); unset($_SESSION['msg']); ?></div>
  <?php endif; ?>

  <div class="row">
    <div class="col s12 m6">
      <!-- Quiz meta -->
      <div class="card">
        <div class="card-content">
          <h6 class="section-title">Quiz details</h6>
          <div class="input-field">
            <input id="quiz_title" type="text">
            <label for="quiz_title">Quiz Title</label>
          </div>
          <div class="input-field">
            <textarea id="quiz_description" class="materialize-textarea"></textarea>
            <label for="quiz_description">Description</label>
          </div>
          <div class="input-field">
            <input id="quiz_deadline" placeholder="YYYY-MM-DD HH:MM:SS or leave empty">
            <label for="quiz_deadline">Deadline (optional)</label>
          </div>
        </div>
      </div>

      <!-- Builder controls -->
      <div class="card">
        <div class="card-content">
          <h6 class="section-title">Form builder (Airport questions only)</h6>

          <div style="margin-bottom:10px;">
            <button class="btn" id="addAirportBtn">Add Airport Question</button>
          </div>

          <div id="questionsContainer"></div>

          <div style="margin-top:12px;">
            <button class="btn" id="saveAllBtn">Save Quiz &amp; Questions</button>
          </div>
        </div>
      </div>

    </div>

    <div class="col s12 m6">
      <!-- Preview area -->
      <div class="card">
        <div class="card-content">
          <h6 class="section-title">Preview / Actions</h6>
          <p class="muted">Add airport questions, adjust prompts if needed, then press <strong>Save Quiz &amp; Questions</strong>.</p>
          <div style="margin-top:12px;">
            <button class="btn grey" id="clearAllBtn">Clear All Questions</button>
            <a class="btn green" href="index.php" style="margin-left:8px;">Back to Home</a>
          </div>
        </div>
      </div>

      <!-- Existing quizzes & questions for reference -->
      <div class="card">
        <div class="card-content">
          <h6 class="section-title">Existing quizzes (recent)</h6>
          <?php
          $q = $mysqli->query("SELECT id,title FROM quizzes ORDER BY created_at DESC LIMIT 8");
          while ($row = $q->fetch_assoc()):
            echo '<div style="margin-bottom:10px;"><strong>'.htmlspecialchars($row['title'])."</strong><br>";
            $qs = $mysqli->prepare("SELECT prompt,question_type FROM questions WHERE quiz_id=? ORDER BY id LIMIT 6");
            $qs->bind_param('i', $row['id']);
            $qs->execute();
            $rs = $qs->get_result();
            echo '<ul>';
            while ($qq = $rs->fetch_assoc()) {
                echo '<li>'.htmlspecialchars($qq['prompt']).' ('.htmlspecialchars($qq['question_type']).')</li>';
            }
            echo '</ul></div>';
            $qs->close();
          endwhile;
          ?>
        </div>
      </div>

    </div>
  </div>

<?php endif; ?>
</div>

<!-- Hidden form used to post compiled JSON to server -->
<form id="saveForm" method="post" style="display:none;">
  <input type="hidden" name="save_quiz" value="1">
  <input type="hidden" name="quiz_title" id="h_quiz_title">
  <input type="hidden" name="quiz_description" id="h_quiz_description">
  <input type="hidden" name="quiz_deadline" id="h_quiz_deadline">
  <input type="hidden" name="questions_json" id="h_questions_json">
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<script>
// Materialize init
document.addEventListener('DOMContentLoaded', function() {
  M.AutoInit();
  fetchCountries();
});

// Utilities
function el(tag, cls, html) {
  const e = document.createElement(tag);
  if (cls) e.className = cls;
  if (html !== undefined) e.innerHTML = html;
  return e;
}
function sanitize(v) { return (v||'').toString(); }

const container = document.getElementById('questionsContainer');
let uidCounter = 1;

// Add Airport
document.getElementById('addAirportBtn').addEventListener('click', () => {
  const id = 'q' + (uidCounter++);
  const card = buildAirportCard(id);
  container.appendChild(card);
  scrollInto(card);
});

// Clear all
document.getElementById('clearAllBtn').addEventListener('click', () => {
  if (!confirm('Remove all question drafts?')) return;
  container.innerHTML = '';
});

// Save all -> compile JSON and submit hidden form
document.getElementById('saveAllBtn').addEventListener('click', async (e) => {
  e.preventDefault();
  const title = document.getElementById('quiz_title').value.trim();
  if (!title) { alert('Quiz title required.'); return; }
  const questions = compileQuestions();
  if (!questions.length) {
    if (!confirm('No questions added. Save empty quiz?')) {
      return;
    }
  }
  document.getElementById('h_quiz_title').value = title;
  document.getElementById('h_quiz_description').value = document.getElementById('quiz_description').value.trim();
  document.getElementById('h_quiz_deadline').value = document.getElementById('quiz_deadline').value.trim();
  document.getElementById('h_questions_json').value = JSON.stringify(questions);
  document.getElementById('saveForm').submit();
});

// compile questions from DOM into objects (airport-only)
function compileQuestions() {
  const items = [];
  const cards = container.querySelectorAll('.qb-card');
  cards.forEach(cd => {
    const type = cd.dataset.type;
    if (type !== 'airport') return;
    const prompt = sanitize(cd.querySelector('.promptInput')?.value);
    const points = parseInt(cd.querySelector('.pointsInput')?.value || 1, 10) || 1;
    const airport_iata = sanitize(cd.querySelector('.airportSelect')?.value);
    const answer_type = cd.querySelector('.airportAnswerType input:checked')?.value || 'iata';
    items.push({type:'airport', airport_iata, answer_type, prompt, points});
  });
  return items;
}

// helpers to build cards
function buildCardShell(id, titleText) {
  const card = el('div', 'card qb-card');
  card.id = id;
  card.dataset.type = 'airport';
  const cont = el('div', 'card-content');
  const title = el('span', 'card-title', titleText);
  cont.appendChild(title);
  const ctrl = el('div', 'right');
  const up = el('button', 'btn-flat small-btn'); up.type='button'; up.title='Move up';
  up.innerHTML = '<i class="material-icons">arrow_upward</i>'; up.addEventListener('click',()=>moveUp(card));
  const down = el('button', 'btn-flat small-btn'); down.type='button'; down.title='Move down';
  down.innerHTML = '<i class="material-icons">arrow_downward</i>'; down.addEventListener('click',()=>moveDown(card));
  const del = el('button', 'btn-flat red-text small-btn'); del.type='button'; del.title='Remove';
  del.innerHTML = '<i class="material-icons">delete</i>'; del.addEventListener('click',()=>{ if(confirm('Remove this question?')) card.remove(); });
  ctrl.appendChild(up); ctrl.appendChild(down); ctrl.appendChild(del);
  title.appendChild(ctrl);
  card.appendChild(cont);
  return {card, cont};
}

function buildAirportCard(id) {
  const {card, cont} = buildCardShell(id, 'Airport question');

  // Country select
  const countryDiv = el('div', 'input-field');
  const countrySel = el('select', 'browser-default countrySelect');
  countrySel.innerHTML = '<option value="" disabled selected>Choose country</option>';
  countryDiv.appendChild(countrySel);
  cont.appendChild(el('div', '', '<label>Country</label>'));
  cont.appendChild(countryDiv);

  // City select
  const cityDiv = el('div', 'input-field');
  const citySel = el('select', 'browser-default citySelect');
  citySel.innerHTML = '<option value="" disabled selected>Choose city</option>';
  cityDiv.appendChild(citySel);
  cont.appendChild(el('div', '', '<label>City</label>'));
  cont.appendChild(cityDiv);

  // Airport select
  const apDiv = el('div', 'input-field');
  const apSel = el('select', 'browser-default airportSelect');
  apSel.innerHTML = '<option value="" disabled selected>Choose airport</option>';
  apDiv.appendChild(apSel);
  cont.appendChild(el('div', '', '<label>Airport</label>'));
  cont.appendChild(apDiv);

  // Answer type radios
  const radios = el('div', 'airportAnswerType');
  radios.innerHTML = '<p><label><input name="atype'+id+'" type="radio" value="iata" checked><span>IATA code</span></label></p>' +
                     '<p><label><input name="atype'+id+'" type="radio" value="name"><span>Airport name</span></label></p>';
  cont.appendChild(radios);

  // Prompt (editable)
  cont.appendChild(el('div', '', '<div class="input-field"><input type="text" class="promptInput"><label>Prompt (auto-generated, editable)</label></div>'));
  cont.appendChild(el('div', '', '<div class="input-field"><input type="number" min="1" value="1" class="pointsInput"><label>Points</label></div>'));

  // wire up dependent selects
  populateCountrySelect(card, countrySel, citySel, apSel);

  apSel.addEventListener('change', () => updateAirportPrompt(card));
  radios.querySelectorAll('input').forEach(r => r.addEventListener('change', () => updateAirportPrompt(card)));

  return card;
}

// move up/down functions
function moveUp(card) { const prev = card.previousElementSibling; if (prev) container.insertBefore(card, prev); }
function moveDown(card) { const next = card.nextElementSibling; if (next) container.insertBefore(next, card); }
function scrollInto(elm) { elm.scrollIntoView({behavior:'smooth', block:'center'}); }

// ---------- Airport selects helpers ----------
let cachedCountries = null;
async function fetchCountries() {
  if (cachedCountries) return cachedCountries;
  const res = await fetch('admin.php?ajax=countries');
  const arr = await res.json();
  cachedCountries = arr;
  return arr;
}

async function populateCountrySelect(card, countrySel, citySel, apSel) {
  const countries = await fetchCountries();
  countrySel.innerHTML = '<option value="" disabled selected>Choose country</option>';
  countries.forEach(c => {
    const o = document.createElement('option'); o.value = c; o.textContent = c;
    countrySel.appendChild(o);
  });
  countrySel.addEventListener('change', async () => {
    const country = countrySel.value;
    citySel.innerHTML = '<option value="" disabled selected>Loading cities...</option>';
    const res = await fetch('admin.php?ajax=cities&country=' + encodeURIComponent(country));
    const cities = await res.json();
    citySel.innerHTML = '<option value="" disabled selected>Choose city</option>';
    cities.forEach(cc => { const o = document.createElement('option'); o.value = cc; o.textContent = cc; citySel.appendChild(o); });
    apSel.innerHTML = '<option value="" disabled selected>Choose airport</option>';
    updateAirportPrompt(card);
  });
  citySel.addEventListener('change', async () => {
    const country = countrySel.value, city = citySel.value;
    apSel.innerHTML = '<option value="" disabled selected>Loading airports...</option>';
    const res = await fetch('admin.php?ajax=airports&country=' + encodeURIComponent(country) + '&city=' + encodeURIComponent(city));
    const arr = await res.json();
    apSel.innerHTML = '<option value="" disabled selected>Choose airport</option>';
    arr.forEach(a => { const o = document.createElement('option'); o.value = a.IATACode; o.textContent = a.AirportName + ' (' + a.IATACode + ')'; apSel.appendChild(o); });
    updateAirportPrompt(card);
  });
  apSel.addEventListener('change', () => updateAirportPrompt(card));
}

function updateAirportPrompt(card) {
  const country = card.querySelector('.countrySelect')?.value || '';
  const city = card.querySelector('.citySelect')?.value || '';
  const apSel = card.querySelector('.airportSelect');
  const iata = apSel?.value || '';
  const apText = apSel?.options[apSel.selectedIndex]?.text || '';
  const airportName = apText.replace(/\s*\([A-Z]{3}\)\s*$/, '');
  const atype = card.querySelector('.airportAnswerType input:checked')?.value || 'iata';
  const pInput = card.querySelector('.promptInput');
  if (atype === 'iata') {
    pInput.value = iata ? 'Type the IATA code for ' + airportName + ' (' + country + ')' : '';
  } else {
    pInput.value = iata ? 'Type the airport name for IATA code ' + iata + ' (' + country + ')' : '';
  }
}

// end script
</script>
</body>
</html>
