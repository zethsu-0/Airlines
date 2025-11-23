<?php
// admin_quiz_maker.php
include('templates/header.php');

// Fetch airports for the IATA select and create a JSON list for JS
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'airlines';

$airportOptionsHtml = '<option value="" disabled selected>Choose IATA code</option>';
$airportList = []; // array of objects for JS autocomplete

$conn = new mysqli($host, $user, $pass, $db);
if (!$conn->connect_error) {
    // Using your actual column names: IATACode, AirportName, City
    $sql = "SELECT IATACode, COALESCE(City,'') AS City, COALESCE(AirportName,'') AS AirportName FROM airports ORDER BY IATACode ASC";
    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $iata = strtoupper(trim($row['IATACode']));
            if ($iata === '') continue;
            $city = trim($row['City']);
            $name = trim($row['AirportName']);
            $labelParts = [];
            if ($city !== '') $labelParts[] = $city;
            if ($name !== '' && stripos($name, $city) === false) $labelParts[] = $name;
            $label = $labelParts ? implode(' — ', $labelParts) : $iata;
            // Option HTML (kept for fallback if needed)
            $opt = '<option value="' . htmlspecialchars($iata) . '" data-city="' . htmlspecialchars(strtoupper($city ?: $name ?: '')) . '">' . htmlspecialchars($iata . ' — ' . $label) . '</option>';
            $airportOptionsHtml .= $opt;

            // Add to JS-friendly list
            $airportList[] = [
                'iata' => $iata,
                'city' => strtoupper($city ?: $name ?: ''),
                'label' => $iata . ' — ' . $label,
                'name' => $name
            ];
        }
        $res->free();
    } else {
        // fallback
        $airportOptionsHtml .= '<option value="MNL" data-city="MANILA">MNL — MANILA</option>';
        $airportOptionsHtml .= '<option value="LAX" data-city="LOS ANGELES">LAX — LOS ANGELES</option>';
        $airportList = [
            ['iata'=>'MNL','city'=>'MANILA','label'=>'MNL — MANILA','name'=>'Manila Airport'],
            ['iata'=>'LAX','city'=>'LOS ANGELES','label'=>'LAX — LOS ANGELES','name'=>'Los Angeles Intl']
        ];
    }
} else {
    // connection error fallback
    $airportOptionsHtml = '<option value="MNL" data-city="MANILA">MNL — MANILA</option>';
    $airportOptionsHtml .= '<option value="LAX" data-city="LOS ANGELES">LAX — LOS ANGELES</option>';
    $airportList = [
        ['iata'=>'MNL','city'=>'MANILA','label'=>'MNL — MANILA','name'=>'Manila Airport'],
        ['iata'=>'LAX','city'=>'LOS ANGELES','label'=>'LAX — LOS ANGELES','name'=>'Los Angeles Intl']
    ];
}

// Provide the options string to JS (safe JSON) and airport list JSON
$airportOptionsJson = json_encode($airportOptionsHtml);
$airportListJson = json_encode($airportList);
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Quiz Maker - Admin</title>
  <!-- Materialize CSS -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css" rel="stylesheet">
  <style>
    :root{ --primary-blue:#0d6efd; --accent-blue:#0b5ed7; --soft-blue:#e9f2ff; }
    body{background:linear-gradient(180deg, #f7fbff 0%, #eef6ff 100%); font-family: Roboto, Arial, sans-serif}
    .page-wrap{max-width:1100px; margin:28px auto;}
    .card.booking{border-radius:14px; box-shadow:0 8px 28px rgba(13,78,191,0.08)}
    .header-hero{background:linear-gradient(90deg,var(--primary-blue),var(--accent-blue)); color:white; padding:18px; border-radius:10px}
    .brand-title{font-weight:700; letter-spacing:0.4px}
    .two-col{display:grid; grid-template-columns: 1fr 420px; gap:16px}
    .flight-row{display:flex; gap:12px; align-items:center}
    .flight-field{flex:1}
    .iatasmall{display:inline-block; font-weight:700; font-size:14px; padding:6px 8px; border-radius:6px; background:rgba(255,255,255,0.12)}
    .boarding-pass{background:linear-gradient(180deg,#ffffff,#f3f7ff); border:1px solid rgba(13,78,191,0.06); padding:14px; border-radius:10px}
    .bp-row{display:flex; justify-content:space-between; align-items:center}
    .bp-airport{font-weight:800; font-size:22px}
    .bp-meta{color:#5f6f94; font-weight:600}
    .btn-primary{background:var(--primary-blue); color:white}
    .small-note{color:#456; font-size:13px}
    @media(max-width:960px){ .two-col{grid-template-columns:1fr} .flight-row{flex-direction:column} }
    .muted { color:#6b7280; font-size:13px; }
    .section-title { font-weight:700; margin-bottom:10px; }
    .card-section { padding:16px; margin-bottom:12px; background:#fff; border-radius:10px; }
    .item-row{border:1px solid rgba(0,0,0,0.06); padding:10px; border-radius:8px; margin-bottom:8px; background:#fbfdff}
    .item-actions{display:flex; gap:6px; align-items:center}

    /* autocomplete dropdown styles */
    .iata-autocomplete {
      position: relative;
    }
    .iata-suggestions {
      position: absolute;
      z-index: 9999;
      left: 0;
      right: 0;
      max-height: 220px;
      overflow: auto;
      border: 1px solid rgba(0,0,0,0.08);
      background: #fff;
      border-radius: 6px;
      box-shadow: 0 6px 18px rgba(0,0,0,0.06);
      margin-top: 6px;
    }
    .iata-suggestion {
      padding: 8px 10px;
      cursor: pointer;
      font-weight:600;
    }
    .iata-suggestion small { display:block; font-weight:400; color:#666; }
    .iata-suggestion:hover { background:#f1f5ff; }
  </style>
</head>
<body>
<div class="page-wrap">

  <div class="header-hero">
    <div style="display:flex; justify-content:space-between; align-items:center">
      <div>
        <div class="brand-title">Quiz Maker — Flight Booking UI</div>
        <div class="small-note">Duration & Booking Ref moved to General Info. Add multiple quiz items (IATA + class + deadline).</div>
      </div>
      <div>
        <a href="Admin.php" class="btn-flat white-text">Back to Dashboard</a>
      </div>
    </div>
  </div>

  <div style="margin-top:18px" class="card booking">
    <div class="card-content">
      <h5 style="margin-top:0">Create New Quiz</h5>

      <div class="two-col" style="margin-top:12px;">
        <!-- LEFT: Main form -->
        <div>
          <!-- GENERAL INFO -->
          <div class="card-section">
            <div class="section-title">General Info</div>
            <div class="input-field">
              <input id="quizTitle" type="text" required>
              <label for="quizTitle">Quiz / Exam Title</label>
              <span class="helper-text">e.g. Midterm Exam — Physics</span>
            </div>

            <div class="input-field">
              <input id="sectionField" type="text">
              <label for="sectionField">Section / Course</label>
              <span class="helper-text">e.g. PHY101 or Section A</span>
            </div>

            <div class="input-field">
              <input id="audienceField" type="text">
              <label for="audienceField">Target Audience</label>
              <span class="helper-text">e.g. All Students, Section B</span>
            </div>

            <!-- Duration and Booking Ref moved here -->
            <div class="flight-row" style="margin-top:8px">
              <div class="flight-field input-field">
                <input id="duration" type="number" min="1" value="60">
                <label for="duration">Duration (minutes)</label>
              </div>

              <div class="flight-field input-field">
                <input id="quizCode" type="text">
                <label for="quizCode">Booking Ref (Quiz Code)</label>
                <span class="helper-text">Auto-generated if left empty</span>
              </div>
            </div>
          </div>

          <!-- QUIZ DETAILS (flight-like) - REPEATABLE ITEMS -->
          <div class="card-section">
            <div style="display:flex; justify-content:space-between; align-items:center">
              <div class="section-title">Quiz Details (Flight-style items)</div>
              <div>
                <a id="addItemBtn" class="btn btn-primary">Add Item</a>
              </div>
            </div>

            <div id="itemsContainer">
              <!-- initial item will be injected by JS -->
            </div>

            <div style="display:flex; gap:10px; align-items:center; margin-top:12px">
              <div style="margin-left:auto" class="small-note">Each item represents one "booking" the student will respond to.</div>
            </div>
          </div>

          <!-- Save / Preview row -->
          <div style="display:flex; gap:10px; align-items:center; margin-top:12px">
            <a id="previewBtn" class="btn btn-primary">Preview Boarding Pass</a>

            <!-- Save buttons required by the JS listeners -->
            <a id="saveQuizBtn" class="btn btn-primary lighten-1">Save Quiz</a>
            <a id="saveAndOpenBtn" class="btn btn-primary">Save & Open</a>

            <div style="margin-left:auto" class="small-note">Admin: make sure to save to persist to DB</div>
          </div>

          <!-- Student prompt preview -->
          <div style="margin-top:12px" class="card-section" id="previewDescriptionWrap" style="display:none;">
            <div class="section-title">Student prompt (preview)</div>
            <div id="bpDescription" style="font-weight:700;"></div>
          </div>
        </div>

        <!-- RIGHT: Boarding pass / stats -->
        <div>
          <div class="card-section">
            <div class="section-title">Boarding Pass Preview</div>
            <div class="boarding-pass" id="boardingPass">
              <div class="bp-row">
                <div>
                  <div class="bp-airport" id="bpFrom">MNL</div>
                  <div class="bp-meta" id="bpFromName">Section</div>
                </div>

                <div style="text-align:center">
                  <div style="font-weight:900; font-size:14px" id="bpTitle">QUIZ/EXAM</div>
                  <div class="bp-meta" id="bpCode">REF: XXXX</div>
                </div>

                <div style="text-align:right">
                  <div class="bp-airport" id="bpTo">LAX</div>
                  <div class="bp-meta" id="bpToName">Audience</div>
                </div>
              </div>

              <div style="margin-top:12px; display:flex; justify-content:space-between; align-items:center">
                <div>
                  <div class="small-note">Departure(s)</div>
                  <div id="bpDeadline" style="font-weight:700"></div>
                </div>
                <div>
                  <div class="small-note">Items • Duration • Class</div>
                  <div id="bpMeta" style="font-weight:700"></div>
                </div>
              </div>

              <div style="margin-top:12px;">
                <div class="muted">Student prompt (description):</div>
                <div id="bpDescriptionRight" style="font-weight:700; margin-top:6px;"></div>
              </div>
            </div>
          </div>

          <div class="card-section">
            <div class="section-title">Quick Info</div>
            <div class="muted">Number of items affects the description. Auto-create student prompt will generate a single text question summarizing all items (expected answer = first item's city).</div>
            <div style="margin-top:8px;">
              <div class="input-field">
                <input id="numQuestions" type="number" min="0" value="0" readonly>
                <label for="numQuestions">Number of Questions (auto)</label>
              </div>
            </div>
          </div>
        </div>
      </div> <!-- two-col -->

    </div> <!-- card-content -->
  </div> <!-- card booking -->

</div> <!-- page-wrap -->

<?php include('templates/footer.php'); ?>

<!-- Materialize + JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<script>
// airport options (HTML string) kept for optional fallbacks
const airportOptionsHtml = <?php echo $airportOptionsJson; ?>; // string of <option>... populated server-side

// structured airport list for JS autocomplete
const airportList = <?php echo $airportListJson; ?>; // [{iata,city,label,name}, ...]

/* Simple matching function:
   returns list of airports where query matches iata, city or name (startsWith or includes).
   Limits results to top 50 to avoid huge lists.
*/
function matchAirports(query){
  if(!query) return [];
  const q = query.trim().toUpperCase();
  const results = [];
  for(const a of airportList){
    if(results.length >= 50) break;
    if(a.iata.startsWith(q) || a.city.startsWith(q) || (a.name && a.name.toUpperCase().startsWith(q))) {
      results.push(a);
      continue;
    }
    // fallback fuzzy includes
    if(a.iata.includes(q) || (a.city && a.city.includes(q)) || (a.name && a.name.toUpperCase().includes(q))) {
      results.push(a);
      continue;
    }
  }
  return results;
}

// utility
function genRef(){ const rand = Math.random().toString(36).substring(2,8).toUpperCase(); return 'QZ-' + rand; }
function uc(s){ return (s || '').toString().trim().toUpperCase(); }

// create item DOM block (autocomplete input instead of select)
let itemIndex = 0;
function createItemBlock(prefill = null){
  const idx = itemIndex++;
  const wrapper = document.createElement('div');
  wrapper.className = 'item-row';
  wrapper.dataset.idx = idx;

  wrapper.innerHTML = `
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px">
      <strong>Item ${idx+1}</strong>
      <div class="item-actions">
        <a class="btn-flat remove-item" title="Remove item"><i class="material-icons">delete</i></a>
      </div>
    </div>

    <div class="flight-row" style="margin-bottom:8px">
      <div class="flight-field input-field iata-autocomplete">
        <input type="text" class="iatalongInputInner" data-idx="${idx}" placeholder="Type IATA, city or airport name" autocomplete="off" />
        <label>IATA Code (type to search)</label>
        <div class="iata-suggestions" style="display:none;"></div>
      </div>

      <div class="flight-field input-field">
        <select class="difficultySelectInner">
          <option value="easy" selected>Easy (Economy)</option>
          <option value="medium">Medium (Premium)</option>
          <option value="hard">Hard (Business)</option>
        </select>
        <label>Seat Class / Difficulty</label>
      </div>
    </div>

    <div class="flight-row">
      <div class="flight-field input-field">
        <input type="datetime-local" class="deadlineInput" />
        <label>Deadline (optional)</label>
      </div>

      <div style="width:120px; display:flex; align-items:center;">
        <span class="muted">Item controls</span>
      </div>
    </div>
  `;

  // remove handler
  wrapper.querySelector('.remove-item').addEventListener('click', (e)=>{
    e.preventDefault();
    wrapper.remove();
    refreshItemLabels();
  });

  // hookup autocomplete handlers
  const input = wrapper.querySelector('.iatalongInputInner');
  const suggBox = wrapper.querySelector('.iata-suggestions');

  // helper to render suggestions
  function renderSuggestions(list){
    if(!suggBox) return;
    if(!list || list.length === 0){
      suggBox.style.display = 'none';
      suggBox.innerHTML = '';
      return;
    }
    suggBox.innerHTML = list.map(a => {
      // include label and city/name in small tag
      const small = a.city ? `<small>${a.city}</small>` : `<small>${a.name || ''}</small>`;
      return `<div class="iata-suggestion" data-iata="${a.iata}" data-city="${a.city || ''}">${a.label}${small}</div>`;
    }).join('');
    suggBox.style.display = 'block';

    // attach click handlers to suggestions
    suggBox.querySelectorAll('.iata-suggestion').forEach(node => {
      node.addEventListener('click', (ev)=>{
        const iata = node.dataset.iata || '';
        const city = node.dataset.city || '';
        input.value = iata;
        input.dataset.city = city;
        // hide
        suggBox.style.display = 'none';
        // if this is first item and expectedCity is empty, set it
        const allItems = Array.from(document.querySelectorAll('#itemsContainer .item-row'));
        const indexOfBlock = allItems.indexOf(wrapper);
        if(indexOfBlock === 0 && city){
          const expectedCityEl = document.getElementById('expectedCity');
          if(expectedCityEl && !expectedCityEl.value.trim()){
            expectedCityEl.value = city;
            M.updateTextFields();
          }
        }
      });
    });
  }

  // typed input -> filter suggestions
  input.addEventListener('input', function(e){
    const q = this.value || '';
    if(q.trim().length === 0) {
      renderSuggestions([]);
      return;
    }
    const matches = matchAirports(q);
    renderSuggestions(matches);
  });

  // close suggestions on outside click
  document.addEventListener('click', function(ev){
    if(!wrapper.contains(ev.target)){
      const b = wrapper.querySelector('.iata-suggestions');
      if(b) b.style.display = 'none';
    }
  });

  // when focus lost, hide suggestions after tiny delay (allow click)
  input.addEventListener('blur', function(){ setTimeout(()=>{ if(suggBox) suggBox.style.display='none'; }, 120); });

  return wrapper;
}

function addItem(prefill = null){
  const cont = document.getElementById('itemsContainer');
  if(!cont) return;
  const block = createItemBlock(prefill);
  cont.appendChild(block);

  // re-init Materialize selects inside the newly added block (for difficulty)
  const selects = block.querySelectorAll('select');
  M.FormSelect.init(selects);

  refreshItemLabels();
}

function refreshItemLabels(){
  const items = Array.from(document.querySelectorAll('#itemsContainer .item-row'));
  items.forEach((it, i)=>{
    const strong = it.querySelector('strong');
    if(strong) strong.textContent = `Item ${i+1}`;
    it.dataset.idx = i;
    const innerInput = it.querySelector('.iatalongInputInner');
    if(innerInput) innerInput.dataset.idx = i;
  });
}

function collectItems(){
  const items = [];
  const blocks = document.querySelectorAll('#itemsContainer .item-row');
  for(const b of blocks){
    const iataInput = b.querySelector('.iatalongInputInner');
    const iata = iataInput ? iataInput.value : '';
    const city = iataInput ? (iataInput.dataset && iataInput.dataset.city ? iataInput.dataset.city : '') : '';
    const difficulty = b.querySelector('.difficultySelectInner') ? b.querySelector('.difficultySelectInner').value : 'easy';
    const deadline = b.querySelector('.deadlineInput') ? b.querySelector('.deadlineInput').value : null;
    items.push({ iata: uc(iata), city: uc(city), difficulty, deadline });
  }
  return items;
}

function buildDescription(){
  const items = collectItems();
  const section = document.getElementById('sectionField').value || '';
  const audience = document.getElementById('audienceField').value || '';
  const duration = document.getElementById('duration').value || '';
  let parts = [];
  for(const it of items){
    if(it.city){
      parts.push(`to ${it.city} (${it.iata}) seat class ${it.difficulty}${it.deadline ? ' by '+(new Date(it.deadline).toLocaleString()) : ''}`);
    } else {
      parts.push(`to destination ${it.iata} seat class ${it.difficulty}${it.deadline ? ' by '+(new Date(it.deadline).toLocaleString()) : ''}`);
    }
  }
  let desc = 'Book ' + (parts.length ? parts.join('; ') : 'the indicated destinations') + '.';
  if(section) desc += ` Course/Section: ${section}.`;
  if(audience) desc += ` Audience: ${audience}.`;
  if(duration) desc += ` Duration: ${duration} minutes.`;
  return { description: desc, expected_answer: (items[0] && items[0].city) ? items[0].city : null, itemsCount: items.length, firstDeadline: (items[0] && items[0].deadline) ? new Date(items[0].deadline).toLocaleString() : '' };
}

/* SAVE function - declared in global scope so event listeners can call it */
async function saveQuiz(redirect=false){
  const items = collectItems();
  const payload = {
    title: document.getElementById('quizTitle').value || 'Untitled Quiz',
    items: items,
    from: document.getElementById('sectionField').value || '',
    to: document.getElementById('audienceField').value || '',
    deadline: null,
    num_questions: 0,
    duration: parseInt(document.getElementById('duration').value || 0, 10),
    difficulty: '',
    code: document.getElementById('quizCode').value || genRef(),
    questions: []
  };

  // auto-create summary question if checked
  const autoCreate = document.getElementById('autoCreateQuestion');
  if(autoCreate && autoCreate.checked){
    const {description, expected_answer} = buildDescription();
    payload.questions.push({
      text: description,
      type: 'text',
      points: 1,
      choices: [],
      expected_answer: expected_answer || null
    });
  }

  payload.num_questions = payload.questions.length;
  document.getElementById('numQuestions').value = payload.num_questions;

  // show a small busy indicator
  M.toast({html: 'Saving quiz...'});
  console.log('Saving payload:', payload);

  try {
    const res = await fetch('save_quiz.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });

    const text = await res.text();
    let data = null;
    try { data = JSON.parse(text); } catch(e) { /* not JSON */ }

    console.log('save_quiz response status:', res.status, 'raw:', text, 'jsonParsed:', data);

    if (!res.ok) {
      const errMsg = data && data.error ? data.error : ('HTTP ' + res.status + ' - ' + (text || 'unknown error'));
      M.toast({html: 'Save failed: ' + errMsg});
      return;
    }

    if (data && data.success) {
      M.toast({html: 'Quiz saved (ID: '+data.id+')'});
      if (redirect) window.location.href = 'Exam.php?id='+data.id;
      else window.location.href = 'Admin.php';
      return;
    }

    const fallbackErr = (data && data.error) ? data.error : ('Unexpected server response: ' + text);
    M.toast({html: 'Save failed: ' + fallbackErr});
  } catch (err) {
    console.error('Network or parse error saving quiz:', err);
    M.toast({html: 'Save failed (network): ' + (err.message || err)});
  }
}

/* All DOM lookups & event bindings inside DOMContentLoaded to avoid "null" errors */
document.addEventListener('DOMContentLoaded', function(){
  // init global selects (only difficulties)
  var elems = document.querySelectorAll('select');
  M.FormSelect.init(elems);

  // Insert initial item
  addItem();

  // Add item button
  const addBtn = document.getElementById('addItemBtn');
  if(addBtn){
    addBtn.addEventListener('click', function(e){
      e.preventDefault();
      addItem();
    });
  }

  // Preview button
  const prevBtn = document.getElementById('previewBtn');
  if(prevBtn){
    prevBtn.addEventListener('click', (e)=>{
      e.preventDefault();
      const title = document.getElementById('quizTitle').value || 'QUIZ/EXAM';
      const section = document.getElementById('sectionField').value || 'Section';
      const audience = document.getElementById('audienceField').value || 'Audience';
      const duration = document.getElementById('duration').value || '60';
      let code = document.getElementById('quizCode').value;
      if(!code) { code = genRef(); document.getElementById('quizCode').value = code; }

      const {description, expected_answer, itemsCount, firstDeadline} = buildDescription();

      // Show summary info: use first item's IATA as representative in BP header (if any)
      const firstItem = collectItems()[0];
      const repIata = firstItem ? firstItem.iata : '---';
      document.getElementById('bpFrom').textContent = repIata;
      document.getElementById('bpFromName').textContent = section;
      document.getElementById('bpTo').textContent = repIata;
      document.getElementById('bpToName').textContent = audience;
      document.getElementById('bpTitle').textContent = title;
      document.getElementById('bpCode').textContent = 'REF: ' + code;
      document.getElementById('bpDeadline').textContent = firstDeadline || 'Multiple / see description';
      document.getElementById('numQuestions').value = document.getElementById('autoCreateQuestion') && document.getElementById('autoCreateQuestion').checked ? 1 : 0;
      document.getElementById('bpMeta').textContent = itemsCount + ' Items • ' + duration + ' min';
      document.getElementById('bpDescription').textContent = description;
      document.getElementById('bpDescriptionRight').textContent = description;

      document.getElementById('boardingPass').style.display = 'block';
      document.getElementById('previewDescriptionWrap').style.display = 'block';
    });
  }

  // Save buttons wiring
  const saveBtn = document.getElementById('saveQuizBtn');
  if(saveBtn){
    saveBtn.addEventListener('click', (e)=>{ e.preventDefault(); saveQuiz(false); });
  }
  const saveAndOpenBtn = document.getElementById('saveAndOpenBtn');
  if(saveAndOpenBtn){
    saveAndOpenBtn.addEventListener('click', (e)=>{ e.preventDefault(); saveQuiz(true); });
  }

}); // end DOMContentLoaded

</script>
</body>
</html>
