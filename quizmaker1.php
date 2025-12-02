<?php
// admin_quiz_maker.php (cleaned, now supports EDIT mode)
include('templates/header_admin.php');

// ---------------- AIRPORTS FOR AUTOCOMPLETE ----------------
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'airlines';

$airportOptionsHtml = '<option value="" disabled selected>Choose IATA code</option>';
$airportList = []; // array of objects for JS autocomplete

$conn = new mysqli($host, $user, $pass, $db);
if (!$conn->connect_error) {
    $sql = "SELECT IATACode, COALESCE(City,'') AS City, COALESCE(AirportName,'') AS AirportName 
            FROM airports 
            ORDER BY IATACode ASC";
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

            $opt = '<option value="' . htmlspecialchars($iata) . '" data-city="' . htmlspecialchars(strtoupper($city ?: $name ?: '')) . '">' . htmlspecialchars($iata . ' — ' . $label) . '</option>';
            $airportOptionsHtml .= $opt;

            $airportList[] = [
                'iata'  => $iata,
                'city'  => strtoupper($city ?: $name ?: ''),
                'label' => $iata . ' — ' . $label,
                'name'  => $name
            ];
        }
        $res->free();
    } else {
        $airportOptionsHtml .= '<option value="MNL" data-city="MANILA">MNL — MANILA</option>';
        $airportOptionsHtml .= '<option value="LAX" data-city="LOS ANGELES">LAX — LOS ANGELES</option>';
        $airportList = [
            ['iata'=>'MNL','city'=>'MANILA','label'=>'MNL — MANILA','name'=>'Manila Airport'],
            ['iata'=>'LAX','city'=>'LOS ANGELES','label'=>'LAX — LOS ANGELES','name'=>'Los Angeles Intl']
        ];
    }
} else {
    $airportOptionsHtml .= '<option value="MNL" data-city="MANILA">MNL — MANILA</option>';
    $airportOptionsHtml .= '<option value="LAX" data-city="LOS ANGELES">LAX — LOS ANGELES</option>';
    $airportList = [
        ['iata'=>'MNL','city'=>'MANILA','label'=>'MNL — MANILA','name'=>'Manila Airport'],
        ['iata'=>'LAX','city'=>'LOS ANGELES','label'=>'LAX — LOS ANGELES','name'=>'Los Angeles Intl']
    ];
}

// JSON for JS
$airportOptionsJson = json_encode($airportOptionsHtml);
$airportListJson    = json_encode($airportList);

// ---------------- EDIT MODE: CHECK FOR ?id= ----------------
$editing   = false;
$editId    = null;

if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
    $editing = true;
    $editId  = (int) $_GET['id'];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $editing ? 'Edit Quiz' : 'Quiz Maker - Admin'; ?></title>
  <!-- Materialize CSS -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css" rel="stylesheet">
  <!-- Material icons -->
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
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

    /* ===== SEAT PICKER STYLES ===== */
    .seat-map {
      display: flex;
      flex-direction: column;
      gap: 8px;
      padding: 16px;
      max-width: 960px;
      margin: 0 auto;
    }

    .seat-row {
      display: flex;
      align-items: center;
      gap: 8px;
      justify-content: center;
    }

    .row-label {
      width: 44px;
      min-width: 44px;
      text-align: center;
      font-weight: 600;
      color: #444;
    }

    .seat {
      width: 44px;
      height: 44px;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      user-select: none;
      border: 1px solid rgba(0,0,0,0.12);
      transition: transform .08s ease, box-shadow .12s ease;
      background: #fff;
      font-weight: 600;
    }
    .seat:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 14px rgba(0,0,0,0.08);
    }

    .seat.selected {
      color: white;
      border-color: rgba(0,0,0,0.15);
    }

    .seat.disabled {
      background: #efefef;
      color: #9e9e9e;
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
    }

    .aisle {
      width: 28px;
      min-width: 28px;
    }

    .legend {
      display:flex;
      gap:12px;
      align-items:center;
      margin: 8px 16px 18px;
      flex-wrap: wrap;
      justify-content: center;
    }
    .legend .box {
      width:18px;height:18px;border-radius:4px;border:1px solid rgba(0,0,0,0.12);
      display:inline-block;vertical-align:middle;margin-right:6px;
    }
    .legend .box.selected { background:#26a69a; border:none; }
    .legend .box.disabled { background:#efefef; color:#9e9e9e; border:none; }

    .selection-summary {
      margin-top: 12px;
      max-width: 960px;
      margin-left: auto;
      margin-right: auto;
      padding: 0 16px 16px;
    }

    .cabin-header {
      margin-top: 10px;
      margin-bottom: 4px;
      text-align: left;
      max-width: 960px;
      margin-left: auto;
      margin-right: auto;
      padding: 0 18px;
      display:flex;
      align-items:center;
      gap:8px;
    }
    .cabin-header h6 {
      margin: 0;
      font-weight: 600;
    }
    .cabin-header .line {
      flex:1;
      height: 1px;
      background: rgba(0,0,0,0.12);
    }

    /* Cabin colors */
    .seat.first    { background-color: #e3f2fd; }  /* light blue */
    .seat.business { background-color: #fff3e0; }  /* light orange */
    .seat.premium  { background-color: #ede7f6; }  /* light purple */
    .seat.economy  { background-color: #e8f5e9; }  /* light green */

    .seat.first.selected    { background-color: #1e88e5; }
    .seat.business.selected { background-color: #fb8c00; }
    .seat.premium.selected  { background-color: #7e57c2; }
    .seat.economy.selected  { background-color: #43a047; }

    .cabin-header.first h6    { color: #1e88e5; }
    .cabin-header.business h6 { color: #fb8c00; }
    .cabin-header.premium h6  { color: #7e57c2; }
    .cabin-header.economy h6  { color: #43a047; }

    @media(max-width:680px){
      .seat { width:36px; height:36px; border-radius:6px; }
      .row-label { width:36px; min-width:36px; font-size:0.9rem; }
    }

    .modal.modal-fixed-footer {
      max-height: 90%;
    }

    /* seat picker button on seat field */
    .seat-picker-btn {
      position:absolute;
      right:0;
      top:32px;
    }
  </style>
</head>
<body>
<div class="page-wrap">

  <div class="header-hero">
    <div style="display:flex; justify-content:space-between; align-items:center">
      <div>
        <div class="brand-title">
          <?php echo $editing ? 'Edit Quiz — Flight Booking UI' : 'Quiz Maker — Flight Booking UI'; ?>
        </div>
        <div class="small-note">
          Duration & Booking Ref in General Info. Add or edit multiple quiz items (IATA + class + deadline).
        </div>
      </div>
      <div>
        <a href="Admin.php" class="btn-flat white-text">Back to Dashboard</a>
      </div>
    </div>
  </div>

  <div style="margin-top:18px" class="card booking">
    <div class="card-content">
      <h5 style="margin-top:0">
        <?php echo $editing ? 'Edit Quiz' : 'Create New Quiz'; ?>
      </h5>
      <div class="col">
        <!-- RIGHT: Boarding pass preview -->
        <div>
          <div class="card-section">
            <div class="section-title">Boarding Pass Preview</div>
            <div class="boarding-pass" id="boardingPass">
              <div class="bp-row">
                <div>
                  <div class="bp-airport" id="bpFrom">MNL</div>
                  <div class="bp-meta" id="bpFromName">Origin</div>
                </div>

                <div style="text-align:center">
                  <div style="font-weight:900; font-size:14px" id="bpTitle">QUIZ/EXAM</div>
                  <div class="bp-meta" id="bpCode">REF: XXXX</div>
                </div>

                <div style="text-align:right">
                  <div class="bp-airport" id="bpTo">LAX</div>
                  <div class="bp-meta" id="bpToName">Destination</div>
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
        </div>
      </div> <!-- col -->

      <div class="col" style="margin-top:12px;">
        <!-- LEFT: Main form -->
        <div>
          <!-- GENERAL INFO -->
          <div class="card-section">
            <div class="section-title">General Info</div>
            <div class="input-field">
              <input id="quizTitle" type="text" required autocomplete="off">
              <label for="quizTitle">Quiz / Exam Title</label>
              <span class="helper-text">e.g. Midterm Exam — Physics</span>
            </div>

            <div class="input-field">
              <input id="sectionField" type="text"  autocomplete="off">
              <label for="sectionField">Section / Course</label>
              <span class="helper-text">e.g. PHY101 or Section A</span>
            </div>
          </div>
                <!-- QUESTION DIRECTION: IATA <-> AIRPORT -->
          <div style="margin-bottom:8px">
            <span class="muted">Question type</span><br>
            <label style="margin-right:12px;">
              <input name="quizInputType" type="radio" value="code-airport" checked />
              <span>IATA CODE -> AIRPORT NAME</span>
            </label>
            <label>
              <input name="quizInputType" type="radio" value="airport-code" />
              <span>AIRPORT NAME -> IATA CODE</span>
            </label>
          </div>
          <!-- ITEMS -->
          <div class="card-section">
            <div style="display:flex; justify-content:space-between; align-items:center">
              <div class="section-title">Quiz Details (Flight-style items)</div>
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

            <a id="saveQuizBtn" class="btn btn-primary lighten-1">
              <?php echo $editing ? 'Update Quiz' : 'Save Quiz'; ?>
            </a>
            <a id="saveAndOpenBtn" class="btn btn-primary">
              <?php echo $editing ? 'Update & Open' : 'Save & Open'; ?>
            </a>

            <div style="margin-left:auto" class="small-note">
              Admin: make sure to <?php echo $editing ? 'update' : 'save'; ?> to persist to DB
            </div>

            <?php if ($editing): ?>
              <a id="deleteQuizBtn" class="btn red darken-2">
                <i class="material-icons left">delete</i>Delete Quiz
              </a>
            <?php endif; ?>
          </div>

          <!-- Student prompt preview -->
          <div style="margin-top:12px" class="card-section" id="previewDescriptionWrap" style="display:none;">
            <div class="section-title">Student prompt (preview)</div>
            <div id="bpDescription" style="font-weight:700;"></div>
          </div>
        </div>    
      </div> <!-- col -->
    </div> <!-- card-content -->
  </div> <!-- card booking -->
</div> <!-- page-wrap -->


<!-- Seat Picker Modal -->
<div id="seatPickerModal" class="modal modal-fixed-footer">
  <div class="modal-content">
    <h5>Seat Picker</h5>
    <p class="grey-text text-darken-1" style="margin-top:-4px;">
      First: rows 1–6 (1–2–1), Business: 7–20 (1–2–1), Premium: 25–27 (2–4–2), Economy: 30–40 (3–4–3)
    </p>

    <!-- Cabin headers injected here -->
    <div id="cabinContainer"></div>

    <!-- Seat map -->
    <div id="seatMap" class="seat-map" aria-label="Seat map" role="application"></div>

    <!-- Legend -->
    <div class="legend">
      <span><span class="box selected"></span> Selected</span>
      <span><span class="box disabled"></span> Taken / Unavailable</span>
    </div>
    <div class="legend">
      <span><span class="box" style="background:#1e88e5"></span> First Class</span>
      <span><span class="box" style="background:#fb8c00"></span> Business Class</span>
      <span><span class="box" style="background:#7e57c2"></span> Premium Economy</span>
      <span><span class="box" style="background:#43a047"></span> Economy</span>
    </div>

    <!-- Selected summary -->
    <div class="selection-summary">
      <h6>Selected seats</h6>
      <div id="selectedChips" class="chips"></div>
      <p id="summaryText" class="grey-text text-darken-1"></p>
    </div>
  </div>

  <div class="modal-footer">
    <a id="clearSeatSelectionBtn" class="btn-flat">Clear</a>
    <a class="modal-close btn" id="seatModalDoneBtn">Done</a>
  </div>
</div>

<?php include('templates/footer.php'); ?>

<!-- Materialize + JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>

<script>
// airport options (HTML string) kept for optional fallbacks
const airportOptionsHtml = <?php echo $airportOptionsJson; ?>;
const airportList        = <?php echo $airportListJson; ?>;

// EDIT MODE FLAGS FROM PHP
const isEditing  = <?php echo $editing ? 'true' : 'false'; ?>;
const editQuizId = <?php echo $editing ? (int)$editId : 'null'; ?>;

/* Simple matching function */
function matchAirports(query){
  if(!query) return [];
  const q = query.trim().toUpperCase();
  const results = [];
  for(const a of airportList){
    if(results.length >= 50) break;
    if(a.iata && a.iata.startsWith(q)) { results.push(a); continue; }
    if(a.city && a.city.startsWith(q)) { results.push(a); continue; }
    if(a.name && a.name.toUpperCase().startsWith(q)) { results.push(a); continue; }
    if(a.iata && a.iata.includes(q)) { results.push(a); continue; }
    if(a.city && a.city.includes(q)) { results.push(a); continue; }
    if(a.name && a.name.toUpperCase().includes(q)) { results.push(a); continue; }
  }
  return results;
}

// Get structured airport info + display texts from airportList
function resolveAirportInfo(value){
  const v = (value || '').trim().toUpperCase();
  if (!v) {
    return {
      code: '---',
      city: '',
      name: '',
      country: '',
      airportText: '---',
      iataText: '---'
    };
  }

  let match = null;
  if (Array.isArray(airportList)) {
    match = airportList.find(a =>
      a.iata === v ||
      a.city === v ||
      (a.name && a.name.toUpperCase() === v)
    );
  }

  let code = v;
  let city = '';
  let name = '';
  let country = '';

  if (match) {
    code    = (match.iata || v).toUpperCase();
    city    = (match.city || '').toUpperCase();
    name    = (match.name || '').toUpperCase();
    // If you later include CountryRegion in PHP, map it here:
    country = (match.country || match.countryRegion || '').toUpperCase();
  }

  // Airport text: ONLY name/city/country – no IATA code here
  const parts = [];
  if (name)    parts.push(name);
  if (city)    parts.push(city);
  if (country) parts.push(country);

  const airportText = parts.length ? parts.join(' - ') : v;
  const iataText    = code;  // just the code

  return { code, city, name, country, airportText, iataText };
}

function genRef(){ const rand = Math.random().toString(36).substring(2,8).toUpperCase(); return 'QZ-' + rand; }
function uc(s){ return (s || '').toString().trim().toUpperCase(); }

let itemIndex = 0;

// ======== SEAT PICKER JS (GLOBAL) ========
const seatMapEl = document.getElementById('seatMap');
const cabinContainerEl = document.getElementById('cabinContainer');
const selectedChipsEl = document.getElementById('selectedChips');
const summaryText = document.getElementById('summaryText');
const clearSeatSelectionBtn = document.getElementById('clearSeatSelectionBtn');
const seatModalDoneBtn = document.getElementById('seatModalDoneBtn');
let seatPickerTargetInput = null;

// Cabin definitions (FIRST + BUSINESS separated)
const CABINS = [
  {
    key: 'first',
    name: 'First Class',
    className: 'first',
    startRow: 1,
    endRow: 6,
    letters: ['A', '', 'D', 'G', '', 'K'] // 1–2–1
  },
  {
    key: 'business',
    name: 'Business Class',
    className: 'business',
    startRow: 7,
    endRow: 20,
    letters: ['A', '', 'D', 'G', '', 'K'] // 1–2–1
  },
  {
    key: 'premium',
    name: 'Premium Economy',
    className: 'premium',
    startRow: 25,
    endRow: 27,
    letters: ['A','B','','D','E','F','G','','J','K'] // 2–4–2
  },
  {
    key: 'economy',
    name: 'Economy',
    className: 'economy',
    startRow: 30,
    endRow: 40,
    letters: ['A','B','C','','D','E','F','G','','H','J','K'] // 3–4–3
  }
];

let selectedSeats = new Set();
let lastClickedSeat = null;
let currentFilterKey = 'all';
let activeCabinKeyForSelection = null;
let maxSeatsForCurrentItem = Infinity;

function createCabinHeader(name, rowsText, className, key) {
  const wrap = document.createElement('div');
  wrap.className = 'cabin-header ' + className;
  wrap.setAttribute('data-cabin-key', key);
  const title = document.createElement('h6');
  title.textContent = name + ' (' + rowsText + ')';
  const line = document.createElement('div');
  line.className = 'line';
  wrap.appendChild(title);
  wrap.appendChild(line);
  return wrap;
}

function generateMultiCabinLayout() {
  if (!seatMapEl || !cabinContainerEl) return;
  seatMapEl.innerHTML = '';
  cabinContainerEl.innerHTML = '';
  selectedSeats.clear();
  updateSeatSummary();

  CABINS.forEach(cabin => {
    const rowsText = cabin.startRow + '–' + cabin.endRow;
    const headerEl = createCabinHeader(cabin.name, rowsText, cabin.className, cabin.key);
    cabinContainerEl.appendChild(headerEl);

    for (let r = cabin.startRow; r <= cabin.endRow; r++) {
      const rowEl = document.createElement('div');
      rowEl.className = 'seat-row';
      rowEl.setAttribute('data-row', r);
      rowEl.setAttribute('data-cabin', cabin.name);
      rowEl.setAttribute('data-cabin-key', cabin.key);

      const rowLabel = document.createElement('div');
      rowLabel.className = 'row-label';
      rowLabel.textContent = r;
      rowEl.appendChild(rowLabel);

      cabin.letters.forEach(part => {
        if (part === '') {
          const aisle = document.createElement('div');
          aisle.className = 'aisle';
          rowEl.appendChild(aisle);
          return;
        }

        const seatId = `${r}${part}`;
        const seatBtn = document.createElement('button');
        seatBtn.type = 'button';
        seatBtn.className = 'seat ' + cabin.className;
        seatBtn.textContent = part;
        seatBtn.setAttribute('data-seat', seatId);
        seatBtn.setAttribute('data-cabin', cabin.name);
        seatBtn.setAttribute('data-cabin-key', cabin.key);
        seatBtn.setAttribute('aria-pressed', 'false');
        seatBtn.setAttribute('title', `${seatId} – ${cabin.name}`);
        seatBtn.setAttribute('aria-label', `Seat ${seatId} in ${cabin.name}`);

        seatBtn.addEventListener('click', (ev) => onSeatClick(ev, seatBtn));
        seatBtn.addEventListener('keydown', (ev) => {
          if (ev.key === 'Enter' || ev.key === ' ') {
            ev.preventDefault();
            seatBtn.click();
          }
        });

        rowEl.appendChild(seatBtn);
      });

      seatMapEl.appendChild(rowEl);
    }
  });

  applyCabinFilter(currentFilterKey);
}

function onSeatClick(ev, seatBtn) {
  if (seatBtn.classList.contains('disabled')) return;

  const seatCabinKey = seatBtn.getAttribute('data-cabin-key');

  // HARD GUARD: must be in the active cabin
  if (activeCabinKeyForSelection && seatCabinKey !== activeCabinKeyForSelection) {
    if (typeof M !== 'undefined' && M.toast) {
      M.toast({html: 'Please pick a seat in the selected class only.'});
    }
    return;
  }

  const seatId = seatBtn.getAttribute('data-seat');

  if (ev.shiftKey && lastClickedSeat) {
    const allSeats = Array.from(document.querySelectorAll('.seat')).filter(s => !s.classList.contains('disabled'));
    const ids = allSeats.map(s => s.getAttribute('data-seat'));
    const i1 = ids.indexOf(lastClickedSeat);
    const i2 = ids.indexOf(seatId);
    if (i1 >= 0 && i2 >= 0) {
      const [start, end] = i1 < i2 ? [i1, i2] : [i2, i1];
      for (let i = start; i <= end; i++) {
        const s = allSeats[i];

        // ENFORCE LIMIT: before selecting new seats
        const alreadySelected = s.classList.contains('selected');
        if (!alreadySelected && selectedSeats.size >= maxSeatsForCurrentItem) {
          if (typeof M !== 'undefined' && M.toast) {
            M.toast({html:`You can only select ${maxSeatsForCurrentItem} seats for this booking.`});
          }
          break;
        }

        toggleSeatSelection(s, true);
      }
    }
  } else {
    // ENFORCE LIMIT: this single seat click
    const isSelected = seatBtn.classList.contains('selected');
    if (!isSelected && selectedSeats.size >= maxSeatsForCurrentItem) {
      if (typeof M !== 'undefined' && M.toast) {
        M.toast({html:`You can only select ${maxSeatsForCurrentItem} seats for this booking.`});
      }
      return;
    }

    toggleSeatSelection(seatBtn, null);
  }

  lastClickedSeat = seatId;
  updateSeatSummary();

  // AUTO CLOSE when we've reached the limit
  if (selectedSeats.size >= maxSeatsForCurrentItem) {
    if (seatPickerTargetInput) {
      const seats = window.getSelectedSeats();
      seatPickerTargetInput.value = seats.join(', ');
      if (typeof M !== 'undefined' && M.updateTextFields) {
        M.updateTextFields();
      }
    }
    if (seatPickerModalInstance && seatPickerModalInstance.close) {
      seatPickerModalInstance.close();
    }
  }
}

function toggleSeatSelection(seatEl, forceSelect = null) {
  const seatId = seatEl.getAttribute('data-seat');
  const isSelected = seatEl.classList.contains('selected');
  const shouldSelect = forceSelect === null ? !isSelected : Boolean(forceSelect);

  if (shouldSelect) {
    seatEl.classList.add('selected');
    seatEl.setAttribute('aria-pressed', 'true');
    selectedSeats.add(seatId);
  } else {
    seatEl.classList.remove('selected');
    seatEl.setAttribute('aria-pressed', 'false');
    selectedSeats.delete(seatId);
  }
}

function updateSeatSummary() {
  if (!selectedChipsEl || !summaryText) return;

  selectedChipsEl.innerHTML = '';

  const seats = Array.from(selectedSeats).sort((a,b) => {
    const re = /^(\d+)(.+)$/;
    const ma = a.match(re);
    const mb = b.match(re);
    const ra = parseInt(ma[1],10), rb = parseInt(mb[1],10);
    if (ra !== rb) return ra - rb;
    return ma[2].localeCompare(mb[2]);
  });

  seats.forEach(s => {
    const chip = document.createElement('div');
    chip.className = 'chip';
    chip.textContent = s;
    const close = document.createElement('i');
    close.className = 'close material-icons';
    close.textContent = 'close';
    close.style.cursor = 'pointer';
    close.addEventListener('click', () => {
      const el = document.querySelector(`[data-seat="${s}"]`);
      if (el) toggleSeatSelection(el, false);
      updateSeatSummary();
    });
    chip.appendChild(close);
    selectedChipsEl.appendChild(chip);
  });

  if (seats.length === 0) {
    summaryText.textContent = 'No seats selected.';
  } else if (seats.length === 1) {
    summaryText.textContent = `You selected seat ${seats[0]}.`;
  } else {
    summaryText.textContent = `You selected ${seats.length} seats: ${seats.join(', ')}.`;
  }
}

function clearSeatSelections() {
  document.querySelectorAll('.seat.selected').forEach(s => {
    s.classList.remove('selected');
    s.setAttribute('aria-pressed', 'false');
  });
  selectedSeats.clear();
  updateSeatSummary();
}

function applyCabinFilter(filterKey) {
  currentFilterKey = filterKey || 'all';

  const rows = document.querySelectorAll('.seat-row');
  const headers = document.querySelectorAll('.cabin-header');

  rows.forEach(row => {
    const key = row.getAttribute('data-cabin-key');
    row.style.display = (key === filterKey) ? 'flex' : 'none';
  });

  headers.forEach(header => {
    const key = header.getAttribute('data-cabin-key');
    header.style.display = (key === filterKey) ? 'flex' : 'none';
  });
}

// expose for debugging / integration if needed
window.getSelectedSeats = () => Array.from(selectedSeats).sort();

// ======== ORIGINAL FORM/ITEM JS ========

let seatPickerModalInstance = null;

function createItemBlock(prefill = null){
  const idx = itemIndex++;
  const wrapper = document.createElement('div');
  wrapper.className = 'item-row';
  wrapper.dataset.idx = idx;

  wrapper.innerHTML = `
    <div class="card-section" style="margin-top:0; padding:10px; background:#fcfeff;">
      <div style="font-weight:700; margin-bottom:8px">Booking Details</div>

      <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:8px; margin-bottom:8px;">
        <div class="input-field"><input type="number" min="0" value="1" class="adultCountInner" data-idx="${idx}" /><label>Adults</label></div>
        <div class="input-field"><input type="number" min="0" value="0" class="childCountInner" data-idx="${idx}" /><label>Children</label></div>
        <div class="input-field"><input type="number" min="0" value="0" class="infantCountInner" data-idx="${idx}" /><label>Infants</label></div>
      </div>

      <div style="margin-bottom:8px">
        <label style="display:block; margin-bottom:6px">Flight type</label>
        <label style="margin-right:8px;"><input name="flightTypeInner${idx}" type="radio" value="ONE-WAY" checked /><span>One-way</span></label>
        <label><input name="flightTypeInner${idx}" type="radio" value="ROUND-TRIP" /><span>Round-trip</span></label>
      </div>
      
      <div class="flight-row" style="margin-bottom:8px">
        <div class="flight-field input-field iata-autocomplete">
          <input type="text" class="originAirportInner" data-idx="${idx}" placeholder="Origin IATA / city" autocomplete="off" />
          <label>Origin Airport</label>
          <div class="iata-suggestions" style="display:none"></div>
        </div>

        <div class="flight-field input-field iata-autocomplete">
          <input type="text" class="destinationAirportInner" data-idx="${idx}" placeholder="Dest IATA / city" autocomplete="off" />
          <label>Destination Airport</label>
          <div class="iata-suggestions" style="display:none"></div>
        </div>
      </div>

      <div class="flight-row" style="margin-bottom:8px">
        <div class="flight-field input-field">
          <input type="date" class="departureDateInner" data-idx="${idx}" />
          <label>Departure date</label>
        </div>
        <div class="flight-field input-field">
          <input type="date" class="returnDateInner" data-idx="${idx}" />
          <label>Return date (if RT)</label>
        </div>
      </div>

      <div class="flight-row" style="margin-bottom:8px">
        <div class="flight-field input-field">
          <input type="text" class="flightNumberInner" data-idx="${idx}" readonly />
          <label>Flight number (autogenerated)</label>
        </div>
        <div class="flight-field input-field" style="position:relative;">
          <input type="text" class="seatNumbersInner" data-idx="${idx}" placeholder="e.g., 14A, 14B" />
          <label>Seat numbers</label>
        </div>
      </div>

      <div class="input-field" style="margin-top:6px;">
        <select class="travelClassInner" data-idx="${idx}">
          <option value="economy" selected>Economy</option>
          <option value="business">Business</option>
          <option value="premium">Premium</option>
          <option value="first">First Class</option>
        </select>
        <label>Class</label>
      </div>
    </div>
  `;

  // NOTE: no more "remove item" button here

  function attachAutocompleteTo(inputEl){
    if(!inputEl) return;
    const container = inputEl.closest('.iata-autocomplete');
    const localSugg = container ? container.querySelector('.iata-suggestions') : null;

    inputEl.addEventListener('input', function(){
      const q = this.value || '';
      if(q.trim().length === 0){
        if(localSugg) { localSugg.style.display='none'; localSugg.innerHTML=''; }
        return;
      }
      const matches = matchAirports(q);
      if(!localSugg) return;
      localSugg.innerHTML = matches.map(a=>{
        const small = a.city ? `<small>${a.city}</small>` : `<small>${(a.name||'').toUpperCase()}</small>`;
        return `<div class="iata-suggestion" data-iata="${a.iata}" data-city="${a.city||''}">${a.label}${small}</div>`;
      }).join('');
      localSugg.style.display = 'block';

      localSugg.querySelectorAll('.iata-suggestion').forEach(node=>{
        node.addEventListener('click', ()=>{
          const iata = node.dataset.iata || '';
          const city = node.dataset.city || '';
          inputEl.value = iata;
          inputEl.dataset.city = (city||'').toUpperCase();
          localSugg.style.display='none';
        });
      });
    });

    inputEl.addEventListener('blur', function(){
      setTimeout(()=>{ if(localSugg) localSugg.style.display='none'; },120);
    });
  }

  // autogenerate flight number for this item
  const flightNumEl = wrapper.querySelector('.flightNumberInner');
  if(flightNumEl && !flightNumEl.value){ 
    flightNumEl.value = 'FL-' + Math.random().toString(36).substring(2,7).toUpperCase(); 
  }

  // attach autocomplete to origin/destination
  attachAutocompleteTo(wrapper.querySelector('.originAirportInner'));
  attachAutocompleteTo(wrapper.querySelector('.destinationAirportInner'));

  // ===== wire seat picker for THIS booking =====
  const seatInput = wrapper.querySelector('.seatNumbersInner');
  if (seatInput) {

    function openSeatPickerForThisItem() {
      seatPickerTargetInput = seatInput;

      const travelSelect = wrapper.querySelector('.travelClassInner');
      const travelClass = (travelSelect && travelSelect.value) ? travelSelect.value : 'economy';

      activeCabinKeyForSelection = travelClass;
      applyCabinFilter(travelClass);

      const adultsEl   = wrapper.querySelector('.adultCountInner');
      const childrenEl = wrapper.querySelector('.childCountInner');
      const infantsEl  = wrapper.querySelector('.infantCountInner');

      const adults   = adultsEl   ? parseInt(adultsEl.value   || '0', 10) || 0 : 0;
      const children = childrenEl ? parseInt(childrenEl.value || '0', 10) || 0 : 0;
      const infants  = infantsEl  ? parseInt(infantsEl.value  || '0', 10) || 0 : 0;

      let totalPeople = adults + children;
      if (totalPeople <= 0) totalPeople = 1;

      maxSeatsForCurrentItem = totalPeople;

      clearSeatSelections();

      const existing = (seatInput.value || '')
        .split(',')
        .map(s => s.trim().toUpperCase())
        .filter(s => s.length > 0);

      if (existing.length) {
        const allSeats = document.querySelectorAll('.seat');
        allSeats.forEach(seatEl => {
          const id = seatEl.getAttribute('data-seat');
          const seatCabinKey = seatEl.getAttribute('data-cabin-key');
          if (existing.includes(id) && seatCabinKey === activeCabinKeyForSelection) {
            toggleSeatSelection(seatEl, true);
          }
        });
        updateSeatSummary();
      }

      if (seatPickerModalInstance) {
        seatPickerModalInstance.open();
      } else if (typeof M !== 'undefined' && M.Modal) {
        const modalElem = document.getElementById('seatPickerModal');
        const instance = M.Modal.getInstance(modalElem) || M.Modal.init(modalElem);
        seatPickerModalInstance = instance;
        seatPickerModalInstance.open();
      }
    }

    seatInput.addEventListener('click', openSeatPickerForThisItem);
    seatInput.addEventListener('focus', openSeatPickerForThisItem);
  }

  // ------------ PREFILL (EDIT MODE) ------------
  if(prefill && prefill.booking){
    const b = prefill.booking;

    const adultsEl   = wrapper.querySelector('.adultCountInner');
    const childrenEl = wrapper.querySelector('.childCountInner');
    const infantsEl  = wrapper.querySelector('.infantCountInner');
    const originEl   = wrapper.querySelector('.originAirportInner');
    const destEl     = wrapper.querySelector('.destinationAirportInner');
    const depEl      = wrapper.querySelector('.departureDateInner');
    const retEl      = wrapper.querySelector('.returnDateInner');
    const seatsEl    = wrapper.querySelector('.seatNumbersInner');
    const travelEl   = wrapper.querySelector('.travelClassInner');
    const radios     = wrapper.querySelectorAll(`input[name="flightTypeInner${idx}"]`);

    if(adultsEl)   adultsEl.value   = b.adults   != null ? b.adults   : 1;
    if(childrenEl) childrenEl.value = b.children != null ? b.children : 0;
    if(infantsEl)  infantsEl.value  = b.infants  != null ? b.infants  : 0;

    const originVal = b.origin || prefill.iata || '';
    const destVal   = b.destination || prefill.city || '';

    if(originEl){ originEl.value = originVal; }
    if(destEl){   destEl.value   = destVal; }

    if(depEl) depEl.value = b.departure || '';
    if(retEl) retEl.value = b.return || '';

    if(flightNumEl && b.flight_number){
      flightNumEl.value = b.flight_number;
    }

    if(seatsEl) seatsEl.value = b.seats || '';

    if(travelEl) travelEl.value = (b.travel_class || 'economy').toLowerCase();

    const ft = (b.flight_type || 'ONE-WAY').toUpperCase();
    radios.forEach(r => {
      if(r.value.toUpperCase() === ft) r.checked = true;
    });
  }

  return wrapper;
}


    // close when clicking overlay
document.addEventListener('click', function (e) {
      if (e.target.classList && e.target.classList.contains('modal-overlay')) {
        if (seatPickerModalInstance && seatPickerModalInstance.close) {
          seatPickerModalInstance.close();
        }
      }
});


function addItem(prefill = null){
  const cont = document.getElementById('itemsContainer');
  if(!cont) return;

  // Always keep only ONE booking for this quiz
  cont.innerHTML = '';

  const block = createItemBlock(prefill);
  cont.appendChild(block);

  const selects = block.querySelectorAll('select');
  M.FormSelect.init(selects);
}


function refreshItemLabels(){
  const items = Array.from(document.querySelectorAll('#itemsContainer .item-row'));
  items.forEach((it, i)=>{
    const strong = it.querySelector('strong');
    if(strong) strong.textContent = `Item ${i+1}`;
    it.dataset.idx = i;
  });
}

function collectItems(){
  const items = [];
  const blocks = document.querySelectorAll('#itemsContainer .item-row');
  for(const b of blocks){
    const adultsEl   = b.querySelector('.adultCountInner');
    const childrenEl = b.querySelector('.childCountInner');
    const infantsEl  = b.querySelector('.infantCountInner');
    const originEl   = b.querySelector('.originAirportInner');
    const destEl     = b.querySelector('.destinationAirportInner');
    const departureEl= b.querySelector('.departureDateInner');
    const returnEl   = b.querySelector('.returnDateInner');
    const flightNumEl= b.querySelector('.flightNumberInner');
    const seatsEl    = b.querySelector('.seatNumbersInner');
    const travelClassEl = b.querySelector('.travelClassInner');
    const flightTypeInput = b.querySelector(`input[name=flightTypeInner${b.dataset.idx}]:checked`);

    const adults = adultsEl ? parseInt(adultsEl.value || 0, 10) : 0;
    const children = childrenEl ? parseInt(childrenEl.value || 0, 10) : 0;
    const infants = infantsEl ? parseInt(infantsEl.value || 0, 10) : 0;
    const origin = originEl ? (originEl.value || '') : '';
    const destination = destEl ? (destEl.value || '') : '';
    const departure = departureEl ? (departureEl.value || null) : null;
    const ret = returnEl ? (returnEl.value || null) : null;
    const flightNumber = flightNumEl ? (flightNumEl.value || '') : '';
    const seats = seatsEl ? (seatsEl.value || '') : '';
    const travelClass = travelClassEl ? (travelClassEl.value || '') : '';
    const ftVal = flightTypeInput ? (flightTypeInput.value || 'ONE-WAY') : 'ONE-WAY';
    const flightType = ftVal.toUpperCase() === 'ROUND-TRIP' ? 'ROUND-TRIP' : 'ONE-WAY';


    items.push({
      iata: uc(origin),
      city: uc(destination),
      booking: {
        adults, children, infants,
        flight_type: flightType,
        origin: uc(origin),
        destination: uc(destination),
        departure, return: ret,
        flight_number: flightNumber,
        seats,
        travel_class: travelClass
      }
    });
  }
  return items;
}

// Turn a code or city into a nicer display using airportList
function resolveAirportDisplay(value){
  const v = (value || '').trim().toUpperCase();
  if (!v) {
    return {
      code: '---',
      city: '',
      name: '',
      country: '',
      airportText: '---',
      iataText: '---'
    };
  }

  let match = null;
  if (Array.isArray(airportList)) {
    match = airportList.find(a =>
      a.iata === v ||
      a.city === v ||
      (a.name && a.name.toUpperCase() === v)
    );
  }

  let code = v;
  let city = '';
  let name = '';
  let country = '';

  if (match) {
    code    = (match.iata || v).toUpperCase();
    city    = (match.city || '').toUpperCase();
    name    = (match.name || '').toUpperCase();
    // optional: if you add CountryRegion in PHP, map it to a.country
    country = (match.country || match.countryRegion || '').toUpperCase();
  }

  // Airport text: NO IATA here, only airport name / city / country
  const parts = [];
  if (name)    parts.push(name);
  if (city)    parts.push(city);
  if (country) parts.push(country);

  const airportText = parts.length ? parts.join(' - ') : v;
  const iataText    = code;  // just the code

  return { code, city, name, country, airportText, iataText };
}

function buildDescription(){
  const items = collectItems();
  const sectionField = document.getElementById('sectionField');
  const section = sectionField ? (sectionField.value || '') : '';

  // Quiz-level type from the radio or from loaded quiz
  const quizTypeRadio = document.querySelector('input[name="quizInputType"]:checked');
  const quizType = quizTypeRadio
    ? quizTypeRadio.value  // 'code-airport' or 'airport-code'
    : (window.currentQuizInputType || 'code-airport');

  let parts = [];

  items.forEach((it) => {
    const b = it.booking || {};

    // ----- PASSENGERS -----
    let passengerParts = [];
    if (b.adults > 0)   passengerParts.push(`${b.adults} adult${b.adults > 1 ? 's' : ''}`);
    if (b.children > 0) passengerParts.push(`${b.children} child${b.children > 1 ? 'ren' : ''}`);
    if (b.infants > 0)  passengerParts.push(`${b.infants} infant${b.infants > 1 ? 's' : ''}`);

    const passengerText = passengerParts.length
      ? passengerParts.join(', ')
      : '1 passenger';

    // ----- AIRPORT VALUE LOOKUP -----
    const originRaw      = b.origin || it.iata || '';
    const destinationRaw = b.destination || it.city || '';

    const originInfo = resolveAirportInfo(originRaw);
    const destInfo   = resolveAirportInfo(destinationRaw);

    // Display depends on quizType:
    // 'airport-code' -> show airport name (student answers with CODE)
    // 'code-airport' -> show IATA code (student answers with AIRPORT)
    let originDisplay = '';
    let destinationDisplay = '';

    if (quizType === 'airport-code') {
      originDisplay      = originInfo.airportText;
      destinationDisplay = destInfo.airportText;
    } else {
      originDisplay      = originInfo.iataText;
      destinationDisplay = destInfo.iataText;
    }

    const flightType  = (b.flight_type || 'ONE-WAY').toUpperCase();
    const flightClass = (b.travel_class || 'ECONOMY').toUpperCase();

    // ----- FINAL PROMPT -----
    const sentence =
      `Book ${passengerText} from ${originDisplay} to ${destinationDisplay}, ` +
      `${flightType}, ${flightClass}.`;

    parts.push(sentence);
  });

  // Join items
  let desc = parts.join(' ');

  // Course / Section
  if (section) {
    desc += ` Course/Section: ${section}.`;
  }

  // Expected answer (helper) based on quizType
  let expected = null;
  if (items.length) {
    const first = items[0];
    const b0 = first.booking || {};

    const originInfo0 = resolveAirportInfo(b0.origin || first.iata || '');
    const destInfo0   = resolveAirportInfo(b0.destination || first.city || '');

    if (quizType === 'airport-code') {
      // Student should answer with IATA code -> use destination code
      expected = destInfo0.code;
    } else {
      // Student should answer with airport name -> use full text
      expected = destInfo0.airportText;
    }
  }

  return { description: desc, expected_answer: expected, itemsCount: items.length };
}

async function saveQuiz(redirect=false){

  const titleEl = document.getElementById('quizTitle');
  const sectionEl = document.getElementById('sectionField');

  const items = collectItems(); // read once

  // 👇 Quiz type from the radio; fallback to loaded type or default
  const quizTypeRadio = document.querySelector('input[name="quizInputType"]:checked');
  const inputType = quizTypeRadio
    ? quizTypeRadio.value               // 'code-airport' or 'airport-code'
    : (window.currentQuizInputType || 'code-airport');

  const title = titleEl ? (titleEl.value || 'Untitled Quiz') : 'Untitled Quiz';
  const fromSection = sectionEl ? (sectionEl.value || '') : '';

  const payload = {
    id:        isEditing ? editQuizId : null,
    quiz_id:   isEditing ? editQuizId : null,
    action:    isEditing ? 'edit'    : 'create',
    mode:      isEditing ? 'edit'    : 'create',
    input_type: inputType,          // ✅ sent to PHP

    title:         title,
    items:         items,
    from:          fromSection,
    num_questions: 0,
    code: isEditing && window.currentQuizCode ? window.currentQuizCode : genRef(),
    questions: []
  };

  M.toast({html: isEditing ? 'Updating quiz...' : 'Saving quiz...'});
  console.log('Saving payload:', payload);

  try {
    const res = await fetch('save_quiz.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });

    const text = await res.text();
    let data = null;
    try { data = JSON.parse(text); } catch(e) {}

    console.log('save_quiz response status:', res.status, 'raw:', text, 'jsonParsed:', data);

    if (!res.ok) {
      const errMsg = data && data.error ? data.error : ('HTTP ' + res.status + ' - ' + (text || 'unknown error'));
      M.toast({html: 'Save failed: ' + errMsg});
      return;
    }

    if (data && data.success) {
      M.toast({html: (isEditing ? 'Quiz updated' : 'Quiz saved') + ' (ID: '+data.id+')'});
      if (redirect) window.location.href = 'Exam.php?id='+data.id;
      return;
    }

    const fallbackErr = (data && data.error) ? data.error : ('Unexpected server response: ' + text);
    M.toast({html: 'Save failed: ' + fallbackErr});
  } catch (err) {
    console.error('Network or parse error saving quiz:', err);
    M.toast({html: 'Save failed (network): ' + (err.message || err)});
  }
}

document.addEventListener('DOMContentLoaded', function(){
  var elems = document.querySelectorAll('select');
  M.FormSelect.init(elems);

  // Init Materialize modal for seat picker (fix selector)
  const modalElems = document.querySelectorAll('#seatPickerModal');
  const instances = M.Modal.init(modalElems);
  if (instances && instances.length) {
    seatPickerModalInstance = instances[0];
  }

  // Build seat layout once
  generateMultiCabinLayout();

  // Cabin filter radio events (if you add them later)
  document.querySelectorAll('input[name="cabinFilter"]').forEach(radio => {
    radio.addEventListener('change', (e) => {
      applyCabinFilter(e.target.value);
    });
  });
  const initialFilter = document.querySelector('input[name="cabinFilter"]:checked');
  if (initialFilter) applyCabinFilter(initialFilter.value);

  // Clear button in modal
  if (clearSeatSelectionBtn) {
    clearSeatSelectionBtn.addEventListener('click', function(e){
      e.preventDefault();
      clearSeatSelections();
    });
  }

  // When user clicks DONE in modal, push selection into target input
  if (seatModalDoneBtn) {
    seatModalDoneBtn.addEventListener('click', function () {
      if (!seatPickerTargetInput) return;
      const chosenSeats = Array.from(selectedSeats).sort();
      const value = chosenSeats.join(', ');
      seatPickerTargetInput.value = value;

      if (typeof M !== 'undefined' && M.updateTextFields) {
        M.updateTextFields();
      }

      if (seatPickerModalInstance && seatPickerModalInstance.close) {
        seatPickerModalInstance.close();
      }
    });
  }

  // Insert at least one item
  addItem();

  const prevBtn = document.getElementById('previewBtn');
  if(prevBtn){
    prevBtn.addEventListener('click', (e)=>{
      e.preventDefault();
      const titleEl = document.getElementById('quizTitle');
      const sectionEl = document.getElementById('sectionField');

      const title = titleEl ? (titleEl.value || 'QUIZ/EXAM') : 'QUIZ/EXAM';
      const section = sectionEl ? (sectionEl.value || 'Section') : 'Section';
      const durationDisplay = '60';
      const code = genRef();

      const {description, itemsCount} = buildDescription();

      const firstItem = collectItems()[0] || null;
      let repFrom = '---', repTo = '---';
      if(firstItem){
        repFrom = (firstItem.booking && firstItem.booking.origin) ? firstItem.booking.origin : (firstItem.iata || '---');
        repTo   = (firstItem.booking && firstItem.booking.destination) ? firstItem.booking.destination : (firstItem.city || '---');
      }

      document.getElementById('bpFrom').textContent = repFrom;
      document.getElementById('bpTo').textContent   = repTo;
      document.getElementById('bpTitle').textContent= title;
      document.getElementById('bpCode').textContent = 'REF: ' + code;
      document.getElementById('bpDeadline').textContent = 'Multiple / see description';

      let metaClass = '';
      if(firstItem && firstItem.difficulty) metaClass = firstItem.difficulty;
      document.getElementById('bpMeta').textContent =
        itemsCount + ' Items • ' + durationDisplay + ' min' + (metaClass ? ' • ' + metaClass : '');

      const finalDesc = description || 'Book the indicated destinations.';
      document.getElementById('bpDescription').textContent = finalDesc;
      document.getElementById('bpDescriptionRight').textContent = finalDesc;

      document.getElementById('boardingPass').style.display = 'block';
      document.getElementById('previewDescriptionWrap').style.display = 'block';
    });
  }

  const saveBtn = document.getElementById('saveQuizBtn');
  if(saveBtn){
    saveBtn.addEventListener('click', (e)=>{ e.preventDefault(); saveQuiz(false); });
  }
  const saveAndOpenBtn = document.getElementById('saveAndOpenBtn');
  if(saveAndOpenBtn){
    saveAndOpenBtn.addEventListener('click', (e)=>{ e.preventDefault(); saveQuiz(true); });
  }

  const deleteBtn = document.getElementById('deleteQuizBtn');
  if (deleteBtn && isEditing && editQuizId) {
    deleteBtn.addEventListener('click', function(e){
      e.preventDefault();
      if (!confirm('Are you sure you want to delete this quiz? This action cannot be undone.')) {
        return;
      }

      M.toast({html:'Deleting quiz...'});

      fetch('delete_quiz.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id: editQuizId })
      })
      .then(r => r.json())
      .then(data => {
        console.log('delete_quiz response:', data);
        if (data && data.success) {
          M.toast({html:'Quiz deleted'});
        } else {
          const err = data && data.error ? data.error : 'Unknown error';
          M.toast({html:'Delete failed: ' + err});
        }
      })
      .catch(err => {
        console.error('Error deleting quiz:', err);
        M.toast({html:'Delete failed (network error)'});
      });
    });
  }
  function applyQuizTypeRadios(inputTypeFromDb) {
  // Normalize to 'code-airport' or 'airport-code'
  let quizType = (inputTypeFromDb || 'code-airport').toString().toLowerCase();

  // Since your DB already stores exactly 'code-airport' / 'airport-code',
  // this is mostly just a safety guard:
  if (quizType !== 'code-airport' && quizType !== 'airport-code') {
    quizType = 'code-airport';
  }

  const radios = document.querySelectorAll('input[name="quizInputType"]');
  radios.forEach(radio => {
    radio.checked = (radio.value.toLowerCase() === quizType);
  });
}

  // ------------------ IF EDITING, LOAD QUIZ DATA ------------------
  if(isEditing && editQuizId){
    M.toast({html:'Loading quiz data...'});
    fetch('load_quiz.php?id=' + encodeURIComponent(editQuizId))
      .then(r => r.json())
      .then(data => {
        console.log('load_quiz response:', data);
        if(!data || !data.success){
          M.toast({html:'Failed to load quiz: ' + (data && data.error ? data.error : 'unknown error')});
          return;
        }

      const q = data.quiz || data;
      window.currentQuizInputType = q.input_type || 'code-airport';
      window.currentQuizCode = q.code || null;

      const titleEl = document.getElementById('quizTitle');
      const sectionEl = document.getElementById('sectionField');

      if (titleEl)  titleEl.value  = q.title || '';
      if (sectionEl) sectionEl.value = q.from || '';

      // update floating labels
      M.updateTextFields();

      // Clear and rebuild items
      const cont = document.getElementById('itemsContainer');
      cont.innerHTML = '';
      itemIndex = 0;

      if (Array.isArray(q.items) && q.items.length) {
        addItem(q.items[0]);
      } else {
        addItem();
      }

      // ✅ Normalize any old input_type values to our two radio values
      let rawType = (q.input_type || 'code-airport').toString().toLowerCase();
      let quizType;

      if (
        rawType === 'airport-code' ||
        rawType === 'airport_to_iata' ||
        rawType === 'airport-to-iata' ||
        rawType === 'airport_to_code'
      ) {
        quizType = 'airport-code';
      } else {
        // fall back to code-airport for anything else (including old iata_to_airport)
        quizType = 'code-airport';
      }

      const quizTypeRadios = document.querySelectorAll('input[name="quizInputType"]');
      quizTypeRadios.forEach(r => {
        r.checked = (r.value === quizType);
      });

      applyQuizTypeRadios(q.input_type);

      M.toast({html:'Quiz loaded'});

      })
      .catch(err => {
        console.error('Error loading quiz:', err);
        M.toast({html:'Error loading quiz data'});
      });
  }

}); // DOMContentLoaded
</script>

</body>
</html>
