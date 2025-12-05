<?php
// quizmaker.php (cleaned, now supports EDIT mode)
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
    $sql = "SELECT 
          IATACode, 
          COALESCE(City,'') AS City, 
          COALESCE(AirportName,'') AS AirportName,
          COALESCE(CountryRegion,'') AS CountryRegion
        FROM airports 
        ORDER BY IATACode ASC";
    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $iata = strtoupper(trim($row['IATACode']));
            if ($iata === '') continue;
            $city = trim($row['City']);
            $name = trim($row['AirportName']);
            $country = trim($row['CountryRegion']);
            $labelParts = [];
            if ($city !== '') $labelParts[] = $city;
            if ($name !== '' && stripos($name, $city) === false) $labelParts[] = $name;
            $label = $labelParts ? implode(' — ', $labelParts) : $iata;

            $opt = '<option value="' . htmlspecialchars($iata) . '" data-city="' . htmlspecialchars(strtoupper($city ?: $name ?: '')) . '">' . htmlspecialchars($iata . ' — ' . $label) . '</option>';
            $airportOptionsHtml .= $opt;

            $airportList[] = [
                'iata'          => $iata,
                'city'          => strtoupper($city ?: $name ?: ''),
                'label'         => $iata . ' — ' . $label,
                'name'          => $name,
                'country'       => strtoupper($country),
                'countryRegion' => strtoupper($country)
            ];

        }
        $res->free();
    } else {
        $airportOptionsHtml .= '<option value="MNL" data-city="MANILA">MNL — MANILA</option>';
        $airportOptionsHtml .= '<option value="LAX" data-city="LOS ANGELES">LAX — LOS ANGELES</option>';
        $airportList = [
            [
                'iata'=>'MNL',
                'city'=>'MANILA',
                'label'=>'MNL — MANILA',
                'name'=>'Ninoy Aquino International Airport',
                'country'=>'PHILIPPINES',
                'countryRegion'=>'PHILIPPINES'
            ],
            [
                'iata'=>'NRT',
                'city'=>'TOKYO',
                'label'=>'NRT - TOKYO',
                'name'=>'Narita International Airport',
                'country'=>'JAPAN',
                'countryRegion'=>'JAPAN'
            ]
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
$editing      = false;
$editPublicId = null;

if (isset($_GET['id']) && $_GET['id'] !== '') {
    $editing      = true;
    // keep only alphanumeric chars for safety
    $editPublicId = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['id']);
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
    .input-error {
      border-bottom: 2px solid #e53935 !important;
      box-shadow: 0 1px 0 0 #e53935 !important;
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
const isEditing    = <?php echo $editing ? 'true' : 'false'; ?>;
const editPublicId = <?php echo $editing ? json_encode($editPublicId) : 'null'; ?>;

/* Simple matching function */
function matchAirports(query){
  if (!query) return [];
  const q = query.trim().toUpperCase();
  const results = [];
  for (const a of airportList) {
    if (results.length >= 50) break;

    const iata    = (a.iata || '').toUpperCase();
    const city    = (a.city || '').toUpperCase();
    const name    = (a.name || '').toUpperCase();
    const country = (a.country || a.countryRegion || '').toUpperCase();

    if (iata && iata.startsWith(q))    { results.push(a); continue; }
    if (city && city.startsWith(q))    { results.push(a); continue; }
    if (name && name.startsWith(q))    { results.push(a); continue; }
    if (country && country.startsWith(q)) { results.push(a); continue; }

    if (iata && iata.includes(q))      { results.push(a); continue; }
    if (city && city.includes(q))      { results.push(a); continue; }
    if (name && name.includes(q))      { results.push(a); continue; }
    if (country && country.includes(q)){ results.push(a); continue; }
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
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px">
      <strong>Item ${idx+1}</strong>
      <div class="item-actions">
        <a class="btn-flat remove-item" title="Remove item"><i class="material-icons">delete</i></a>
      </div>
    </div>

    <div class="card-section" style="margin-top:10px; padding:10px; background:#fcfeff;">
      <div style="font-weight:700; margin-bottom:8px">Booking Details (Item ${idx+1})</div>

      <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:8px; margin-bottom:8px;">
        <div class="input-field"><input type="number" min="0" value="1" class="adultCountInner" data-idx="${idx}" /><label>Adults</label></div>
        <div class="input-field"><input type="number" min="0" value="0" class="childCountInner" data-idx="${idx}" /><label>Children</label></div>
        <div class="input-field"><input type="number" min="0" value="0" class="infantCountInner" data-idx="${idx}" /><label>Infants</label></div>
      </div>

      <div style="margin-bottom:8px">
        <label style="display:block; margin-bottom:6px">Flight type</label>
        <label style="margin-right:8px;"><input name="flightTypeInner${idx}" type="radio" value="ONE-WAY" checked /><span>One-way</span></label>
        <label style="margin-right:8px;"><input name="flightTypeInner${idx}" type="radio" value="ROUND-TRIP" /><span>Round-trip</span></label>
        <label><input name="flightTypeInner${idx}" type="radio" value="MULTI-CITY" /><span>Multi-city</span></label>
      </div>

      <!-- SINGLE ORIGIN/DESTINATION (used for ONE-WAY / ROUND-TRIP) -->
      <div class="flight-row single-origin-dest" style="margin-bottom:8px;">
        <div class="flight-field input-field iata-autocomplete" style="flex:1;">
          <input type="text" class="originAirportInner" data-idx="${idx}" placeholder="Origin IATA / city" autocomplete="off" />
          <label>Origin Airport</label>
          <div class="iata-suggestions" style="display:none"></div>
        </div>

        <div class="flight-field input-field iata-autocomplete" style="flex:1;">
          <input type="text" class="destinationAirportInner" data-idx="${idx}" placeholder="Dest IATA / city" autocomplete="off" />
          <label>Destination Airport</label>
          <div class="iata-suggestions" style="display:none"></div>
        </div>
      </div>

      <!-- MULTI-CITY legs area (hidden for ONE-WAY / ROUND-TRIP by default) -->
      <div class="multi-legs" data-idx="${idx}" style="margin-bottom:8px; display:none;">
        <div class="legs-list">
          <div class="leg-row" data-leg-index="0" style="display:flex; gap:8px; align-items:center; margin-bottom:6px;">
            <div class="flight-field input-field iata-autocomplete" style="flex:1;">
              <input type="text" class="legOrigin" data-idx="${idx}" data-leg="0" placeholder="Origin IATA / city" autocomplete="off" />
              <label>Origin</label>
              <div class="iata-suggestions" style="display:none"></div>
            </div>
            <div class="flight-field input-field iata-autocomplete" style="flex:1;">
              <input type="text" class="legDestination" data-idx="${idx}" data-leg="0" placeholder="Dest IATA / city" autocomplete="off" />
              <label>Destination</label>
              <div class="iata-suggestions" style="display:none"></div>
            </div>
            <div style="width:160px;">
              <div class="input-field">
                <input type="date" class="legDate" data-idx="${idx}" data-leg="0" />
                <label>Date</label>
              </div>
            </div>
            <a class="btn-flat remove-leg" title="Remove leg" style="display:none;"><i class="material-icons">remove_circle</i></a>
          </div>
        </div>

        <div style="display:flex; gap:8px; margin-top:6px; align-items:center;">
          <a class="btn-flat add-leg" style="display:none;"><i class="material-icons">add_circle</i> Add leg</a>
          <span class="muted" style="margin-left:auto;">Multi-city: each leg has its own datepicker.</span>
        </div>
      </div>

      <!-- SINGLE-LEG DATES (used for ONE-WAY and ROUND-TRIP).
           Note: for MULTI-CITY we will use per-leg .legDate inputs and hide this row. -->
      <div class="flight-row single-leg-dates" style="margin-bottom:8px">
        <div class="flight-field input-field" style="flex:1;">
          <input type="date" class="departureDateInner" data-idx="${idx}" />
          <label>Departure date</label>
        </div>
        <div class="flight-field input-field" style="flex:1;">
          <input type="date" class="returnDateInner" data-idx="${idx}" />
          <label>Return date (used for Round-trip)</label>
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

  // remove handler
  wrapper.querySelector('.remove-item').addEventListener('click', (e)=>{
    e.preventDefault();
    wrapper.remove();
    refreshItemLabels();
  });

  // ----------------- AUTOCOMPLETE ATTACH -----------------
  // Attach to single origin/dest (ONE-WAY / ROUND-TRIP)
  attachAutocompleteTo(wrapper.querySelector('.originAirportInner'));
  attachAutocompleteTo(wrapper.querySelector('.destinationAirportInner'));

  // Attach to initial leg origin/destination (if user switches to MULTI-CITY)
  attachAutocompleteTo(wrapper.querySelector('.legOrigin'));
  attachAutocompleteTo(wrapper.querySelector('.legDestination'));

  // ----------------- FLIGHT NUMBER -----------------
  const flightNumEl = wrapper.querySelector('.flightNumberInner');
  if(flightNumEl && !flightNumEl.value){
    flightNumEl.value = 'FL-' + Math.random().toString(36).substring(2,7).toUpperCase();
  }

  // ----------------- SEAT PICKER HOOKUP -----------------
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

      const existing = (seatInput.value || '').split(',').map(s => s.trim().toUpperCase()).filter(s => s.length > 0);
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

  // ----------------- PREFILL (EDIT MODE) -----------------
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

    if (Array.isArray(b.legs) && b.legs.length && (b.flight_type || '').toUpperCase() === 'MULTI-CITY') {
      // populate legs
      const legsList = wrapper.querySelector('.legs-list');
      legsList.innerHTML = '';
      b.legs.forEach((lg, li) => {
        addLegRowToItem(wrapper, idx);
        const rows = legsList.querySelectorAll('.leg-row');
        const last = rows[rows.length - 1];
        if (last) {
          const orig = last.querySelector('.legOrigin');
          const dest = last.querySelector('.legDestination');
          const date = last.querySelector('.legDate');
          if (orig) orig.value = (lg.origin || '').toUpperCase();
          if (dest) dest.value = (lg.destination || '').toUpperCase();
          if (date) date.value = lg.date || '';
        }
      });
    } else {
      // fill single origin/destination and dates
      if(originEl){ originEl.value = b.origin || (prefill.iata || ''); }
      if(destEl){   destEl.value   = b.destination || (prefill.city || ''); }

      if(depEl) depEl.value = b.departure || '';
      if(retEl) retEl.value = b.return || '';

      // also copy departure into first leg date for compatibility
      const firstLegDate = wrapper.querySelector('.legs-list .leg-row .legDate');
      if (firstLegDate && b.departure) firstLegDate.value = b.departure;
    }

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
    if (prefill) {
      wrapper._loadedFromEdit = true;
  }
  // wire flight-type controls (handles toggling single/multi and showing/hiding return date)
    wireItemFlightTypeControls(wrapper);
    if (typeof wireItemFlightTypeControls === 'function') {
    try { wireItemFlightTypeControls(block); } catch(e) { console.warn(e); }
  }
  // ensure autocomplete attached after prefill too (covers dynamic inputs)
  wrapper.querySelectorAll('input.iata-autocomplete, input.legOrigin, input.legDestination, input.originAirportInner, input.destinationAirportInner').forEach(inp => {
    if (inp) attachAutocompleteTo(inp);
  });

  return wrapper;
}


function addLegRowToItem(itemBlock, idx){
  if (!itemBlock) {
    console.error('addLegRowToItem: missing itemBlock');
    return null;
  }
  const legsList = itemBlock.querySelector('.legs-list');
  if (!legsList) {
    console.error('addLegRowToItem: legs-list not found in itemBlock', itemBlock);
    return null;
  }

  const legIndex = legsList.querySelectorAll('.leg-row').length;
  const legRow = document.createElement('div');
  legRow.className = 'leg-row';
  legRow.dataset.legIndex = legIndex;
  legRow.style = 'display:flex; gap:8px; align-items:center; margin-bottom:6px;';

  legRow.innerHTML = `
    <div class="flight-field input-field iata-autocomplete" style="flex:1;">
      <input type="text" class="legOrigin" data-idx="${idx}" data-leg="${legIndex}" placeholder="Origin IATA / city" autocomplete="off" />
      <label>Origin</label>
      <div class="iata-suggestions" style="display:none"></div>
    </div>
    <div class="flight-field input-field iata-autocomplete" style="flex:1;">
      <input type="text" class="legDestination" data-idx="${idx}" data-leg="${legIndex}" placeholder="Dest IATA / city" autocomplete="off" />
      <label>Destination</label>
      <div class="iata-suggestions" style="display:none"></div>
    </div>
    <div style="width:160px;">
      <div class="input-field">
        <input type="date" class="legDate" data-idx="${idx}" data-leg="${legIndex}" />
        <label>Date</label>
      </div>
    </div>
    <a class="btn-flat remove-leg" title="Remove leg"><i class="material-icons">remove_circle</i></a>
  `;

  legsList.appendChild(legRow);

  // attach autocomplete to the newly created inputs
  const lo = legRow.querySelector('.legOrigin');
  const ld = legRow.querySelector('.legDestination');
  if (lo) attachAutocompleteTo(lo);
  if (ld) attachAutocompleteTo(ld);

  // update Materialize floating labels if available
  if (typeof M !== 'undefined' && M.updateTextFields) {
    try { M.updateTextFields(); } catch(e) {}
  }

  return legRow;
}


// Delegated click handlers + improved wireItemFlightTypeControls
function wireItemFlightTypeControls(wrapper){
  if (!wrapper) return;
  const idx = wrapper.dataset.idx;
  const radios = wrapper.querySelectorAll(`input[name="flightTypeInner${idx}"]`);
  const addLegBtn = wrapper.querySelector('.add-leg');
  const legsContainer = wrapper.querySelector('.multi-legs');
  const singleDatesRow = wrapper.querySelector('.single-leg-dates');
  const singleOriginDest = wrapper.querySelector('.single-origin-dest');
  const returnDateEl = wrapper.querySelector('.returnDateInner');

  // remember previous mode to avoid repeating resets
  wrapper._lastFlightType = wrapper._lastFlightType || null;

  function show(el){ if(el) el.style.display = ''; }
  function hide(el){ if(el) el.style.display = 'none'; }

  function refreshUI(forceNoReset = false){
    const chosen = Array.from(radios).find(r => r.checked);
    const mode = chosen ? (chosen.value || '').toUpperCase() : 'ONE-WAY';
    const prev = wrapper._lastFlightType;

    // reset fields if mode changed and not coming from edit/prefill
    if (!forceNoReset && prev && prev !== mode && !wrapper._loadedFromEdit) {
      // only reset when switching between types (avoid clearing on initial set)
      try {
        // reset only the fields relevant to the switch direction
        if (mode === 'MULTI-CITY') {
          // clear single inputs when moving to multi
          const origin = wrapper.querySelector('.originAirportInner');
          const dest = wrapper.querySelector('.destinationAirportInner');
          const dep = wrapper.querySelector('.departureDateInner');
          const ret = wrapper.querySelector('.returnDateInner');
          if (origin) origin.value = '';
          if (dest) dest.value = '';
          if (dep) dep.value = '';
          if (ret) ret.value = '';
          // clear legs then create single blank leg
          const legsList = wrapper.querySelector('.legs-list');
          if (legsList) {
            legsList.innerHTML = '';
            addLegRowToItem(wrapper, idx);
          }
        } else {
          // mode is ONE-WAY or ROUND-TRIP: remove multi legs
          const legsList = wrapper.querySelector('.legs-list');
          if (legsList) {
            legsList.innerHTML = '';
            // recreate single blank leg for compatibility (keeps the DOM stable)
            addLegRowToItem(wrapper, idx);
          }
          // clear return if switching to ONE-WAY
          if (mode === 'ONE-WAY') {
            const retEl = wrapper.querySelector('.returnDateInner');
            if (retEl) retEl.value = '';
          }
        }
      } catch (err) {
        console.warn('Error resetting fields when switching flight type', err);
      }
    }

    wrapper._lastFlightType = mode;

    // UI switch
    if (mode === 'MULTI-CITY') {
      hide(singleOriginDest);
      hide(singleDatesRow);
      if (legsContainer) show(legsContainer);
      if (addLegBtn) show(addLegBtn);

      // show remove buttons for extras, hide for first
      wrapper.querySelectorAll('.legs-list .remove-leg').forEach((el, i) => {
        el.style.display = (i === 0) ? 'none' : '';
      });
    } else if (mode === 'ROUND-TRIP') {
      if (singleOriginDest) show(singleOriginDest);
      if (singleDatesRow) show(singleDatesRow);
      if (addLegBtn) hide(addLegBtn);
      if (legsContainer) hide(legsContainer);
      // show return
      if (returnDateEl) {
        const wrap = returnDateEl.closest('.flight-field') || returnDateEl;
        show(wrap);
      }
      // hide remove buttons
      wrapper.querySelectorAll('.legs-list .remove-leg').forEach(el => el.style.display = 'none');
    } else {
      // ONE-WAY
      if (singleOriginDest) show(singleOriginDest);
      if (singleDatesRow) show(singleDatesRow);
      if (addLegBtn) hide(addLegBtn);
      if (legsContainer) hide(legsContainer);
      if (returnDateEl) {
        const wrap = returnDateEl.closest('.flight-field') || returnDateEl;
        hide(wrap);
      }
      wrapper.querySelectorAll('.legs-list .remove-leg').forEach(el => el.style.display = 'none');
    }

    if (typeof M !== 'undefined' && M.updateTextFields) M.updateTextFields();
  }

  // bind radio changes
  radios.forEach(r => r.addEventListener('change', () => refreshUI(false)));

  // Delegated click handler on wrapper for add-leg and remove-leg
  // This ensures it works for dynamically created buttons.
  // Remove existing delegated handler if present
  if (wrapper._delegatedHandler) wrapper.removeEventListener('click', wrapper._delegatedHandler);

  const delegated = function(e){
    const target = e.target;
    // find closest .add-leg or .remove-leg
    const addBtn = target.closest ? target.closest('.add-leg') : null;
    if (addBtn && wrapper.contains(addBtn)) {
      e.preventDefault();
      // ensure visible and not disabled
      if (getComputedStyle(addBtn).display === 'none') return;
      addLegRowToItem(wrapper, idx);
      // make sure remove icons visibility updated
      wrapper.querySelectorAll('.legs-list .remove-leg').forEach((el, i) => {
        el.style.display = (i === 0) ? 'none' : '';
      });
      return;
    }

    const remBtn = target.closest ? target.closest('.remove-leg') : null;
    if (remBtn && wrapper.contains(remBtn)) {
      e.preventDefault();
      // remove the parent leg-row
      const legRow = remBtn.closest('.leg-row');
      if (legRow) {
        legRow.remove();
        // reindex remaining legs
        const legs = wrapper.querySelectorAll('.legs-list .leg-row');
        legs.forEach((lr, i) => {
          lr.dataset.legIndex = i;
          lr.querySelectorAll('input').forEach(inp => inp.dataset.leg = i);
        });
        // update remove button visibility
        wrapper.querySelectorAll('.legs-list .remove-leg').forEach((el, i) => {
          el.style.display = (i === 0) ? 'none' : '';
        });
      }
      return;
    }
  };

  wrapper.addEventListener('click', delegated);
  wrapper._delegatedHandler = delegated;

  // initial call (don't reset fields right away when wiring — pass true)
  refreshUI(true);
}

// ====== Delegated Add/Remove Leg handler (global) ======
// This ensures Add leg always works even if per-item delegated wiring failed.
(function(){
  // Avoid adding multiple times
  if (window.__LEG_DELEGATION_ADDED) return;
  window.__LEG_DELEGATION_ADDED = true;

  document.addEventListener('click', function delegatedLegHandler(e){
    // Find clicked add-leg or remove-leg button (works when clicking icon or anchor)
    const addBtn = e.target.closest ? e.target.closest('.add-leg') : null;
    if (addBtn) {
      // find the item-row parent
      const item = addBtn.closest('.item-row');
      if (!item) {
        console.warn('Add leg clicked but .item-row parent not found');
        return;
      }

      // visible check
      if (getComputedStyle(addBtn).display === 'none') {
        console.warn('Add leg clicked but button is hidden.');
        return;
      }

      console.log('Add leg clicked for item idx=', item.dataset.idx);

      // call your function to actually add a leg row
      try {
        addLegRowToItem(item, item.dataset.idx);
        // show remove buttons correctly
        item.querySelectorAll('.legs-list .remove-leg').forEach((el, i) => {
          el.style.display = (i === 0) ? 'none' : '';
        });
      } catch (err) {
        console.error('Error in addLegRowToItem:', err);
      }

      return;
    }

    const remBtn = e.target.closest ? e.target.closest('.remove-leg') : null;
    if (remBtn) {
      const item = remBtn.closest('.item-row');
      if (!item) {
        console.warn('Remove leg clicked but .item-row parent not found');
        return;
      }
      console.log('Remove leg clicked for item idx=', item.dataset.idx);
      const legRow = remBtn.closest('.leg-row');
      if (legRow) {
        legRow.remove();
        // reindex
        const legs = item.querySelectorAll('.legs-list .leg-row');
        legs.forEach((lr, i) => {
          lr.dataset.legIndex = i;
          lr.querySelectorAll('input').forEach(inp => inp.dataset.leg = i);
        });
        // update remove button visibility
        item.querySelectorAll('.legs-list .remove-leg').forEach((el, i) => {
          el.style.display = (i === 0) ? 'none' : '';
        });
      }
      return;
    }
  }, false);

})();


// ----------------- wireItemFlightTypeControls -----------------
// Shows/hides single-origin/dates vs multi-legs depending on flight type radios
// wrapper: the .item-row element
function resetItemFields(wrapper){
  // SINGLE inputs
  const origin = wrapper.querySelector('.originAirportInner');
  const dest   = wrapper.querySelector('.destinationAirportInner');
  const dep    = wrapper.querySelector('.departureDateInner');
  const ret    = wrapper.querySelector('.returnDateInner');

  if (origin) origin.value = '';
  if (dest) dest.value = '';
  if (dep) dep.value = '';
  if (ret) ret.value = '';

  // MULTI-CITY legs
  const legsList = wrapper.querySelector('.legs-list');
  if (legsList) {
    legsList.innerHTML = ''; // remove all legs
    // recreate the first blank leg row
    addLegRowToItem(wrapper, wrapper.dataset.idx);
  }

  if (typeof M !== 'undefined' && M.updateTextFields) M.updateTextFields();
}

function resetBoardingPassPreview() {
  const bpFrom       = document.getElementById('bpFrom');
  const bpFromName   = document.getElementById('bpFromName');
  const bpTo         = document.getElementById('bpTo');
  const bpToName     = document.getElementById('bpToName');
  const bpTitle      = document.getElementById('bpTitle');
  const bpCode       = document.getElementById('bpCode');
  const bpDeadline   = document.getElementById('bpDeadline');
  const bpDescription= document.getElementById('bpDescription');
  const bpDescRight  = document.getElementById('bpDescriptionRight');
  const bpMeta       = document.getElementById('bpMeta');
  const bpContainer  = document.getElementById('boardingPass');
  const previewWrap  = document.getElementById('previewDescriptionWrap');

  // Reset primary fields
  if (bpFrom)       bpFrom.textContent = '---';
  if (bpFromName)   bpFromName.textContent = 'Origin';
  if (bpTo)         bpTo.textContent = '---';
  if (bpToName)     bpToName.textContent = 'Destination';
  if (bpTitle)      bpTitle.textContent = 'QUIZ / EXAM';
  if (bpCode)       bpCode.textContent = 'REF: ----';
  if (bpDeadline)   bpDeadline.textContent = '---';
  if (bpMeta)       bpMeta.textContent = '---';

  if (bpDescription) bpDescription.textContent = '';
  if (bpDescRight)   bpDescRight.textContent = '';

  // Hide preview blocks
  if (bpContainer)  bpContainer.style.display = 'none';
  if (previewWrap)  previewWrap.style.display = 'none';

  console.log('%c[RESET] Boarding Pass Preview cleared.', 'color:#cc2222');
}


function wireItemFlightTypeControls(wrapper){
  const idx = wrapper.dataset.idx;
  const radios = wrapper.querySelectorAll(`input[name="flightTypeInner${idx}"]`);

  const singleOriginDest = wrapper.querySelector('.single-origin-dest');
  const singleDatesRow   = wrapper.querySelector('.single-leg-dates');
  const returnDateEl     = wrapper.querySelector('.returnDateInner');
  const addLegBtn        = wrapper.querySelector('.add-leg');
  const legsContainer    = wrapper.querySelector('.multi-legs');

  // track previous mode to prevent double-reset
  wrapper._lastFlightType = wrapper._lastFlightType || 'ONE-WAY';

  function show(el){ if(el) el.style.display=''; }
  function hide(el){ if(el) el.style.display='none'; }

  function refreshUI(){
    const selected = Array.from(radios).find(r => r.checked);
    const mode = selected ? selected.value.toUpperCase() : 'ONE-WAY';

    const wasMode = wrapper._lastFlightType;
    wrapper._lastFlightType = mode;

    // ---------------------------------------------------------
    // AUTO-RESET FIELDS WHEN SWITCHING FLIGHT TYPE
    // (skip reset if same mode OR if wrapper has data via EDIT MODE)
    // ---------------------------------------------------------
    const hasPrefilledData = wrapper._loadedFromEdit === true;

    if (!hasPrefilledData && mode !== wasMode) {
      resetItemFields(wrapper);
    }

    // ---------------------------------------------------------
    // UI MODE SWITCHING
    // ---------------------------------------------------------

    if (mode === 'MULTI-CITY') {
      hide(singleOriginDest);
      hide(singleDatesRow);
      show(legsContainer);
      if (addLegBtn) show(addLegBtn);

      // show remove buttons (except first)
      wrapper.querySelectorAll('.legs-list .remove-leg').forEach((el, i)=>{
        el.style.display = (i === 0 ? 'none' : 'inline-flex');
      });

    } else if (mode === 'ROUND-TRIP') {
      show(singleOriginDest);
      show(singleDatesRow);
      hide(legsContainer);
      if (addLegBtn) hide(addLegBtn);

      // show return date
      if (returnDateEl) show(returnDateEl.closest('.flight-field'));

      wrapper.querySelectorAll('.legs-list .remove-leg').forEach(el=>{
        el.style.display='none';
      });

    } else { // ONE-WAY
      show(singleOriginDest);
      show(singleDatesRow);
      hide(legsContainer);
      if (addLegBtn) hide(addLegBtn);

      // hide return date
      if (returnDateEl) hide(returnDateEl.closest('.flight-field'));

      wrapper.querySelectorAll('.legs-list .remove-leg').forEach(el=>{
        el.style.display='none';
      });
    }

    if (typeof M !== 'undefined' && M.updateTextFields) M.updateTextFields();
  }

  radios.forEach(r =>
    r.addEventListener('change', refreshUI)
  );

  // initial render
  refreshUI();
}

function attachAutocompleteTo(inputEl) {
  if (!inputEl) return;
  // Friendly debug label
  const debug = (msg, ...args) => { if (window.console) console.debug('[iata-autocomplete]', msg, ...args); };

  // ensure container wrapper exists (prefer a wrapper with .iata-autocomplete, else use parent)
  let container = inputEl.closest('.iata-autocomplete');
  if (!container) {
    debug('No .iata-autocomplete wrapper found; using input.parentNode and creating wrapper class.');
    container = inputEl.parentNode;
    if (container) container.classList.add('iata-autocomplete');
  }

  // ensure suggestion container exists (create if needed)
  let localSugg = container ? container.querySelector('.iata-suggestions') : null;
  if (!localSugg) {
    debug('Creating .iata-suggestions element because none was found.');
    localSugg = document.createElement('div');
    localSugg.className = 'iata-suggestions';
    // minimal inline styles to make it visible by default for debugging; you can remove/replace with CSS
    localSugg.style.position = 'absolute';
    localSugg.style.zIndex = 9999;
    localSugg.style.background = '#fff';
    localSugg.style.border = '1px solid #ddd';
    localSugg.style.maxHeight = '260px';
    localSugg.style.overflow = 'auto';
    localSugg.style.width = (inputEl.offsetWidth || 300) + 'px';
    localSugg.style.display = 'none';
    // append right after input for good positioning
    container.appendChild(localSugg);
  }

  // --- utilities ---
  function escapeHtml(unsafe) {
    return String(unsafe || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function safeCallMatch(q) {
    try {
      if (typeof matchAirports === 'function') {
        const out = matchAirports(q);
        if (Array.isArray(out) && out.length) return out;
      }
    } catch (e) {
      debug('matchAirports threw:', e);
    }
    return [];
  }

  // Try to preload window.allAirports automatically if not already present.
  // Uses the current path with ?airports=1 — adapt if your endpoint is different.
  async function ensureAllAirportsLoaded() {
    if (Array.isArray(window.allAirports) && window.allAirports.length) {
      return window.allAirports;
    }
    try {
      debug('Attempting to preload window.allAirports from endpoint ?airports=1');
      const res = await fetch(window.location.pathname + '?airports=1', { cache: 'no-store' });
      if (!res.ok) throw new Error('network not ok ' + res.status);
      const data = await res.json();
      if (Array.isArray(data) && data.length) {
        window.allAirports = data.map(a => ({
          iata: (a.code || a.iata || a.IATA || '').toUpperCase(),
          city: a.city || a.cityName || '',
          name: a.name || a.airportName || '',
          country: a.country || a.countryRegion || ''
        }));
        debug('Preloaded', window.allAirports.length, 'airports into window.allAirports');
        return window.allAirports;
      }
    } catch (err) {
      debug('Preload failed:', err);
    }
    return [];
  }

  // getMatches: robust fallbacks, returns Array
function getMatchesSync(q) {
  q = (q === undefined || q === null) ? '' : String(q);
  const isEmptyQuery = !q.trim();

  // 1) If matchAirports exists and returns results for this q, use it
  try {
    if (typeof matchAirports === 'function') {
      const out = matchAirports(q);
      if (Array.isArray(out) && out.length) return out.slice(0, 200);
    }
  } catch (e) {
    debug && console.debug && console.debug('[iata-autocomplete] matchAirports threw', e);
  }

  // 2) If the query is empty, use the server-rendered airportList (synchronous fallback)
  if (isEmptyQuery && Array.isArray(airportList) && airportList.length) {
    // return a shallow copy limited to avoid huge DOM inserts
    return airportList.slice(0, 200);
  }

  // 3) If non-empty, but matchAirports returned nothing, try simple contains search on airportList
  if (!isEmptyQuery && Array.isArray(airportList) && airportList.length) {
    const qq = q.trim().toUpperCase();
    const out = [];
    for (const a of airportList) {
      const iata = (a.iata || '').toUpperCase();
      const city = (a.city || '').toUpperCase();
      const name = (a.name || '').toUpperCase();
      const country = (a.country || a.countryRegion || '').toUpperCase();
      if (out.length >= 200) break;
      if (iata.startsWith(qq) || city.startsWith(qq) || name.startsWith(qq) || country.startsWith(qq)) {
        out.push(a); continue;
      }
      if (iata.includes(qq) || city.includes(qq) || name.includes(qq) || country.includes(qq)) {
        out.push(a); continue;
      }
    }
    if (out.length) return out;
  }

  // 4) last-ditch: if window.allAirports exists, use it
  if (Array.isArray(window.allAirports) && window.allAirports.length) {
    if (isEmptyQuery) return window.allAirports.slice(0, 200);
    const qq = q.trim().toLowerCase();
    return window.allAirports.filter(a =>
      (a.iata && a.iata.toLowerCase().includes(qq)) ||
      (a.city && a.city.toLowerCase().includes(qq)) ||
      (a.name && a.name.toLowerCase().includes(qq)) ||
      (a.country && a.country.toLowerCase().includes(qq))
    ).slice(0,200);
  }

  // Nothing found
  return [];
}

  // async version that will try to preload if needed
  async function getMatches(q) {
    let m = getMatchesSync(q);
    if (m.length) return m;
    // attempt preloading window.allAirports once
    const pre = await ensureAllAirportsLoaded();
    if (pre && pre.length) {
      // If query present, do a simple contains filter
      if (q && q.trim()) {
        const qq = q.trim().toLowerCase();
        return pre.filter(a =>
          (a.iata && a.iata.toLowerCase().includes(qq)) ||
          (a.city && a.city.toLowerCase().includes(qq)) ||
          (a.name && a.name.toLowerCase().includes(qq)) ||
          (a.country && a.country.toLowerCase().includes(qq))
        ).slice(0, 200);
      }
      return pre.slice(0, 200);
    }
    return [];
  }

  // render suggestions
  function renderSuggestions(matches) {
    if (!localSugg) return;
    if (!matches || matches.length === 0) {
      localSugg.innerHTML = '<div class="no-results">No airports found</div>';
      localSugg.style.display = 'block';
      return;
    }
    localSugg.innerHTML = matches.map(a => {
      const label = escapeHtml(a.label || (a.iata || '') + ' ' + (a.name || ''));
      const small = a.city ? `<small>${escapeHtml(a.city)}</small>` : `<small>${escapeHtml((a.name || '').toUpperCase())}</small>`;
      return `<div class="iata-suggestion"
                   data-iata="${escapeHtml(a.iata || '')}"
                   data-city="${escapeHtml(a.city || '')}"
                   data-name="${escapeHtml(a.name || '')}"
                   data-country="${escapeHtml(a.country || '')}">
                ${label}${small}
              </div>`;
    }).join('');
    localSugg.style.display = 'block';
  }

  // show default (called on focus/click)
  async function showDefault() {
    debug('showDefault called for', inputEl.id || inputEl.name || inputEl);
    const matches = await getMatches('');
    debug('Default matches length:', matches.length);
    renderSuggestions(matches);
  }

  // handle typing
  let inputTimeout = null;
  inputEl.addEventListener('input', function () {
    if (inputTimeout) clearTimeout(inputTimeout);
    // small debounce to avoid spamming matchAirports
    inputTimeout = setTimeout(async () => {
      const q = this.value || '';
      if (!q.trim()) {
        // show defaults rather than hide
        await showDefault();
        return;
      }
      const matches = await getMatches(q);
      debug('input -> matches length', matches.length, 'for query:', q);
      renderSuggestions(matches);
    }, 120);
  });

  // open suggestions on focus & click
  inputEl.addEventListener('focus', () => { showDefault(); });
  inputEl.addEventListener('click', () => { showDefault(); });

  // delegate click events on container
  localSugg.addEventListener('click', (evt) => {
    const node = evt.target.closest('.iata-suggestion');
    if (!node) return;
    const quizTypeRadio = document.querySelector('input[name="quizInputType"]:checked');
    const quizType = quizTypeRadio ? quizTypeRadio.value : (window.currentQuizInputType || 'code-airport');

    const iata = (node.dataset.iata || '').toUpperCase();
    const city = (node.dataset.city || '').toUpperCase();
    const name = (node.dataset.name || '').toUpperCase();
    const country = (node.dataset.country || '').toUpperCase();

    if (quizType === 'airport-code') {
      const parts = [];
      if (name) parts.push(name);
      if (city) parts.push(city);
      if (country) parts.push(country);
      const display = parts.join(' - ') || iata;
      inputEl.value = display;
    } else {
      inputEl.value = iata;
    }

    inputEl.dataset.city = city;
    localSugg.style.display = 'none';
    if (typeof M !== 'undefined' && M.updateTextFields) M.updateTextFields();
    inputEl.dispatchEvent(new Event('change', { bubbles: true }));

    triggerAirportValidation(inputEl);
  });

  // hide on blur (allow click)
  inputEl.addEventListener('blur', function () {
    setTimeout(() => { if (localSugg) localSugg.style.display = 'none'; }, 120);
  });
  inputEl.addEventListener("input", () => {
    triggerAirportValidation(inputEl);
  });
  // initia l debug ping
  debug('attachAutocompleteTo initialized for', inputEl, 'container', container, 'suggestions', !!localSugg);
}

function triggerAirportValidation(el) {
    const wrapper = el.closest('.item-row');
    if (!wrapper) return;

    const idx = wrapper.dataset.idx;

    // single
    const orig = wrapper.querySelector('.originAirportInner');
    const dest = wrapper.querySelector('.destinationAirportInner');

    validateAirportPair(orig, dest);

    // multi-city legs
    wrapper.querySelectorAll('.leg-row').forEach(row => {
        const lo = row.querySelector('.legOrigin');
        const ld = row.querySelector('.legDestination');
        validateAirportPair(lo, ld);
    });
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
  const block = createItemBlock(prefill);

  // append FIRST (important for Materialize initialization)
  cont.appendChild(block);

  // Initialize Materialize selects and other components inside this newly appended block
  try {
    const selects = block.querySelectorAll('select');
    if (selects && selects.length && typeof M !== 'undefined' && M.FormSelect) {
      M.FormSelect.init(selects);
    }
  } catch (err) {
    console.warn('Materialize select init error on appended item:', err);
  }

  // re-run updateTextFields so floating labels position correctly
  if (typeof M !== 'undefined' && M.updateTextFields) {
    try { M.updateTextFields(); } catch(e) { /* ignore */ }
  }

  // If your createItemBlock doesn't attach autocomplete inside, attach it now
  // (optional safety)
  attachAutocompleteTo(block.querySelector('.originAirportInner'));
  attachAutocompleteTo(block.querySelector('.destinationAirportInner'));
  attachAutocompleteTo(block.querySelector('.legOrigin'));
  attachAutocompleteTo(block.querySelector('.legDestination'));

  refreshItemLabels();
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

  blocks.forEach((b) => {
    const idx = b.dataset.idx;

    const adultsEl   = b.querySelector('.adultCountInner');
    const childrenEl = b.querySelector('.childCountInner');
    const infantsEl  = b.querySelector('.infantCountInner');
    const flightNumEl= b.querySelector('.flightNumberInner');
    const seatsEl    = b.querySelector('.seatNumbersInner');
    const travelClassEl = b.querySelector('.travelClassInner');
    const flightTypeInput = b.querySelector(`input[name="flightTypeInner${idx}"]:checked`);

    const adults = adultsEl ? parseInt(adultsEl.value || 0, 10) : 0;
    const children = childrenEl ? parseInt(childrenEl.value || 0, 10) : 0;
    const infants = infantsEl ? parseInt(infantsEl.value || 0, 10) : 0;
    const flightNumber = flightNumEl ? (flightNumEl.value || '') : '';
    const seats = seatsEl ? (seatsEl.value || '') : '';
    const travelClass = travelClassEl ? (travelClassEl.value || '') : '';
    const ftVal = flightTypeInput ? (flightTypeInput.value || 'ONE-WAY') : 'ONE-WAY';
    const flightType = (ftVal || 'ONE-WAY').toUpperCase();

    // Normalize helper
    const ucNorm = (s) => (s || '').toString().trim().toUpperCase();

    // If MULTI-CITY => collect all leg rows
    if (flightType === 'MULTI-CITY') {
      const legs = [];
      const legRows = b.querySelectorAll('.legs-list .leg-row');
      legRows.forEach((lr) => {
        const originEl = lr.querySelector('.legOrigin');
        const destEl = lr.querySelector('.legDestination');
        const dateEl = lr.querySelector('.legDate');

        const origin = originEl ? (originEl.value || '') : '';
        const destination = destEl ? (destEl.value || '') : '';
        const date = dateEl ? (dateEl.value || null) : null;

        legs.push({
          origin: ucNorm(origin),
          destination: ucNorm(destination),
          date
        });
      });

      const topOrigin = legs.length ? legs[0].origin : '';
      const topDestination = legs.length ? legs[legs.length - 1].destination : '';

      items.push({
        iata: ucNorm(topOrigin),
        city: ucNorm(topDestination),
        booking: {
          adults, children, infants,
          flight_type: flightType,
          origin: ucNorm(topOrigin),
          destination: ucNorm(topDestination),
          departure: legs.length ? legs[0].date : null,
          return: null,
          flight_number: flightNumber,
          seats,
          travel_class: travelClass,
          legs
        }
      });

    } else {
      // ONE-WAY / ROUND-TRIP: read the single origin/destination + date(s)
      const originEl = b.querySelector('.originAirportInner');
      const destEl   = b.querySelector('.destinationAirportInner');
      const depEl    = b.querySelector('.departureDateInner');
      const retEl    = b.querySelector('.returnDateInner');

      const originRaw = originEl ? (originEl.value || '') : '';
      const destRaw = destEl ? (destEl.value || '') : '';
      const departure = depEl ? (depEl.value || null) : null;
      const ret = retEl ? (retEl.value || null) : null;

      const origin = ucNorm(originRaw);
      const destination = ucNorm(destRaw);

      // create single-leg array so consumers can always look at booking.legs
      const legs = [{ origin, destination, date: departure }];

      items.push({
        iata: origin,
        city: destination,
        booking: {
          adults, children, infants,
          flight_type: flightType,
          origin,
          destination,
          departure,
          return: flightType === 'ROUND-TRIP' ? ret : null,
          flight_number: flightNumber,
          seats,
          travel_class: travelClass,
          legs
        }
      });
    }
  });

  // DEBUG helper: uncomment to inspect collected items in console
  console.log('collectItems ->', items);

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

  const quizTypeRadio = document.querySelector('input[name="quizInputType"]:checked');
  const quizType = quizTypeRadio ? quizTypeRadio.value : (window.currentQuizInputType || 'code-airport');

  let parts = [];

  items.forEach((it) => {
    const b = it.booking || {};

    // passengers
    let passengerParts = [];
    if (b.adults > 0)   passengerParts.push(`${b.adults} adult${b.adults > 1 ? 's' : ''}`);
    if (b.children > 0) passengerParts.push(`${b.children} child${b.children > 1 ? 'ren' : ''}`);
    if (b.infants > 0)  passengerParts.push(`${b.infants} infant${b.infants > 1 ? 's' : ''}`);
    const passengerText = passengerParts.length ? passengerParts.join(', ') : '1 passenger';

    const flightType  = (b.flight_type || 'ONE-WAY').toUpperCase();
    const flightClass = (b.travel_class || 'ECONOMY').toUpperCase();

    // build route text
    let routeText = '';

    if (flightType === 'MULTI-CITY' && Array.isArray(b.legs) && b.legs.length) {
      // Build something like "MNL → NRT (2025-06-01), NRT → LAX (2025-06-05)"
      const segs = b.legs.map(l => {
        const originInfo = resolveAirportInfo(l.origin || '');
        const destInfo = resolveAirportInfo(l.destination || '');
        const originDisplay = (quizType === 'airport-code') ? originInfo.airportText : originInfo.iataText;
        const destDisplay = (quizType === 'airport-code') ? destInfo.airportText : destInfo.iataText;
        const datePart = l.date ? ` (${l.date})` : '';
        return `${originDisplay} → ${destDisplay}${datePart}`;
      });
      routeText = segs.join(', ');
    } else {
      // Single leg (first leg)
      const firstOrigin = (Array.isArray(b.legs) && b.legs[0]) ? b.legs[0].origin : (b.origin || it.iata || '');
      const firstDest = (Array.isArray(b.legs) && b.legs[0]) ? b.legs[0].destination : (b.destination || it.city || '');
      const originInfo = resolveAirportInfo(firstOrigin);
      const destInfo   = resolveAirportInfo(firstDest);
      const originDisplay = (quizType === 'airport-code') ? originInfo.airportText : originInfo.iataText;
      const destDisplay = (quizType === 'airport-code') ? destInfo.airportText : destInfo.iataText;
      const datePart = (Array.isArray(b.legs) && b.legs[0] && b.legs[0].date) ? ` on ${b.legs[0].date}` : (b.departure ? ` on ${b.departure}` : '');
      routeText = `${originDisplay} to ${destDisplay}${datePart}`;
      if (flightType === 'ROUND-TRIP' && b.return) routeText += ` (return ${b.return})`;
    }

    const sentence = `Book ${passengerText} — ${routeText}, ${flightType}, ${flightClass}.`;
    parts.push(sentence);
  });

  let desc = parts.join(' ');

  if (section) {
    desc += ` Course/Section: ${section}.`;
  }

  // expected answer: keep same logic but pick last destination of first item
  let expected = null;
  if (items.length) {
    const first = items[0];
    const b0 = first.booking || {};
    let targetDest = '';
    if (Array.isArray(b0.legs) && b0.legs.length) {
      targetDest = b0.legs[b0.legs.length - 1].destination || '';
    } else {
      targetDest = b0.destination || first.city || '';
    }
    const destInfo0 = resolveAirportInfo(targetDest || '');
    expected = (quizType === 'airport-code') ? destInfo0.code : destInfo0.airportText;
  }

  return { description: desc, expected_answer: expected, itemsCount: items.length };
}


async function saveQuiz(redirect=false){

    // FINAL VALIDATION PASS
    let allValid = true;

    document.querySelectorAll('.item-row').forEach(item => {
        // SINGLE
        const orig = item.querySelector('.originAirportInner');
        const dest = item.querySelector('.destinationAirportInner');
        if (!validateAirportPair(orig, dest)) allValid = false;

        // MULTI-CITY
        item.querySelectorAll('.leg-row').forEach(row => {
            const lo = row.querySelector('.legOrigin');
            const ld = row.querySelector('.legDestination');
            if (!validateAirportPair(lo, ld)) allValid = false;
        });
    });

    if (!allValid) {
        M.toast({ html: "Origin and destination cannot be the same." });
        return;
    }

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
    id:        isEditing ? editPublicId : null,
    quiz_id:   isEditing ? editPublicId : null,
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
    const pid = data.public_id || editPublicId || data.id; // fallback to old id if needed
    M.toast({html: (isEditing ? 'Quiz updated' : 'Quiz saved') + ' (ID: ' + pid + ')'});
    if (redirect && pid) {
      window.location.href = 'Exam.php?id=' + encodeURIComponent(pid);
    }
    return;
    }

    const fallbackErr = (data && data.error) ? data.error : ('Unexpected server response: ' + text);
    M.toast({html: 'Save failed: ' + fallbackErr});
  } catch (err) {
    console.error('Network or parse error saving quiz:', err);
    M.toast({html: 'Save failed (network): ' + (err.message || err)});
  }
}

function resetAllItemFieldsBecauseQuestionTypeChanged(){
  const items = document.querySelectorAll('#itemsContainer .item-row');

  items.forEach(item => {
    const idx = item.dataset.idx;

    // Single route inputs
    const origin = item.querySelector('.originAirportInner');
    const dest   = item.querySelector('.destinationAirportInner');
    const dep    = item.querySelector('.departureDateInner');
    const ret    = item.querySelector('.returnDateInner');
    const seats  = item.querySelector('.seatNumbersInner');

    if (origin) origin.value = '';
    if (dest)   dest.value = '';
    if (dep)    dep.value = '';
    if (ret)    ret.value = '';
    if (seats)  seats.value = '';

    // MULTI-CITY legs
    const legsList = item.querySelector('.legs-list');
    if (legsList) {
      legsList.innerHTML = '';
      addLegRowToItem(item, idx);   // recreate FIRST leg row
    }
  });

  // update floating labels
  if (typeof M !== 'undefined' && M.updateTextFields) {
    M.updateTextFields();
  }

  console.log('%c[RESET] Question type changed — all item fields cleared.', 'color:#d42;');
}

function validateAirportPair(originEl, destEl) {
    if (!originEl || !destEl) return true;

    const o = (originEl.value || "").trim().toUpperCase();
    const d = (destEl.value || "").trim().toUpperCase();

    // remove previous errors
    originEl.classList.remove("input-error");
    destEl.classList.remove("input-error");

    // allow empty (user still typing)
    if (!o || !d) return true;

    if (o === d) {
        originEl.classList.add("input-error");
        destEl.classList.add("input-error");
        return false;
    }
    return true;
}

document.addEventListener('DOMContentLoaded', function(){

  document.querySelectorAll('input[name="quizInputType"]').forEach(radio => {
  radio.addEventListener('change', () => {
    resetAllItemFieldsBecauseQuestionTypeChanged();
    resetBoardingPassPreview();                     
  });
});

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

  const addBtn = document.getElementById('addItemBtn');
  if(addBtn){
    addBtn.addEventListener('click', function(e){
      e.preventDefault();
      addItem();
    });
  }

  const prevBtn = document.getElementById('previewBtn');
  if (prevBtn) {
    prevBtn.addEventListener('click', (e) => {
      e.preventDefault();

      const titleEl = document.getElementById('quizTitle');
      const sectionEl = document.getElementById('sectionField');

      const title = titleEl ? (titleEl.value || 'QUIZ/EXAM') : 'QUIZ/EXAM';
      const section = sectionEl ? (sectionEl.value || 'Section') : 'Section';
      const durationDisplay = '60';
      const code = genRef();

      // collect items (reads current UI)
      const items = collectItems();
      if (!items || !items.length) {
        if (typeof M !== 'undefined' && M.toast) M.toast({ html: 'No items to preview.' });
        return;
      }

      const { description, itemsCount } = buildDescription();

      // pick the first item for the big boarding pass
      const firstItem = items[0] || null;
      const b0 = firstItem && firstItem.booking ? firstItem.booking : {};

      // respect quiz type: show codes vs names
      const quizTypeRadio = document.querySelector('input[name="quizInputType"]:checked');
      const quizType = quizTypeRadio ? quizTypeRadio.value : (window.currentQuizInputType || 'code-airport');

      // Determine originRaw and destRaw robustly:
      let originRaw = '';
      let destRaw = '';

      // If it's multi-city and has legs, prefer legs[0].origin and legs[last].destination
      if ((b0.flight_type || '').toUpperCase() === 'MULTI-CITY' && Array.isArray(b0.legs) && b0.legs.length) {
        originRaw = b0.legs[0].origin || '';
        destRaw = b0.legs[b0.legs.length - 1].destination || '';
      } else {
        // For ONE-WAY / ROUND-TRIP: prefer booking.origin/destination if present,
        // otherwise fall back to legs[0]
        originRaw = b0.origin || firstItem.iata || (Array.isArray(b0.legs) && b0.legs[0] ? b0.legs[0].origin : '') || '';
        destRaw   = b0.destination || firstItem.city || (Array.isArray(b0.legs) && b0.legs[0] ? b0.legs[0].destination : '') || '';
      }

      // Resolve display text using your helper
      const originInfo = resolveAirportInfo(originRaw);
      const destInfo   = resolveAirportInfo(destRaw);

      let repFrom = '---', repTo = '---';
      let repFromSub = 'Origin';
      let repToSub   = 'Destination';

      if (quizType === 'airport-code') {
        // show big airport name, small IATA
        repFrom    = originInfo.airportText || originInfo.iataText || '---';
        repTo      = destInfo.airportText   || destInfo.iataText   || '---';
        repFromSub = originInfo.iataText || 'Origin';
        repToSub   = destInfo.iataText   || 'Destination';
      } else {
        // show big IATA, small airport name
        repFrom    = originInfo.iataText || originInfo.airportText || '---';
        repTo      = destInfo.iataText || destInfo.airportText || '---';
        repFromSub = originInfo.airportText || 'Origin';
        repToSub   = destInfo.airportText || 'Destination';
      }

      // Compute Deadline / Departure(s) string
      let deadlineText = 'Multiple / see description';
      try {
        const ft = (b0.flight_type || 'ONE-WAY').toUpperCase();
        if (ft === 'MULTI-CITY' && Array.isArray(b0.legs) && b0.legs.length) {
          // list each leg date (compact)
          const segDates = b0.legs.map(l => {
            const d = l.date || '';
            const o = resolveAirportInfo(l.origin || '');
            const dv = resolveAirportInfo(l.destination || '');
            // short form: ORIG→DEST (date)
            const oShort = (quizType === 'airport-code') ? (o.airportText || o.iataText) : o.iataText || o.airportText;
            const dShort = (quizType === 'airport-code') ? (dv.airportText || dv.iataText) : dv.iataText || dv.airportText;
            return `${oShort}→${dShort}${d ? ' (' + d + ')' : ''}`;
          });
          deadlineText = segDates.join(', ');
        } else if (ft === 'ROUND-TRIP') {
          const dep = b0.departure || (Array.isArray(b0.legs) && b0.legs[0] ? b0.legs[0].date : '');
          const ret = b0.return || '';
          if (dep && ret) deadlineText = `Departs ${dep} • Returns ${ret}`;
          else if (dep) deadlineText = `Departs ${dep}`;
          else deadlineText = 'Dates: see item';
        } else { // ONE-WAY
          const dep = b0.departure || (Array.isArray(b0.legs) && b0.legs[0] ? b0.legs[0].date : '');
          deadlineText = dep ? `Departs ${dep}` : 'Departure date: not set';
        }
      } catch (err) {
        deadlineText = 'Multiple / see description';
      }

      // Update boarding pass DOM
      const bpFromEl = document.getElementById('bpFrom');
      const bpToEl = document.getElementById('bpTo');
      const bpFromNameEl = document.getElementById('bpFromName');
      const bpToNameEl = document.getElementById('bpToName');

      if (bpFromEl) bpFromEl.textContent = repFrom;
      if (bpToEl) bpToEl.textContent = repTo;
      if (bpFromNameEl) bpFromNameEl.textContent = repFromSub;
      if (bpToNameEl) bpToNameEl.textContent = repToSub;

      document.getElementById('bpTitle').textContent = title;
      document.getElementById('bpCode').textContent = 'REF: ' + code;
      document.getElementById('bpDeadline').textContent = deadlineText;

      let metaClass = '';
      if (firstItem && firstItem.difficulty) metaClass = firstItem.difficulty;
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
  if (deleteBtn && isEditing && editPublicId) {
    deleteBtn.addEventListener('click', function(e){
      e.preventDefault();
      if (!confirm('Are you sure you want to delete this quiz? This action cannot be undone.')) {
        return;
      }

      M.toast({html:'Deleting quiz...'});
      
      fetch('delete_quiz.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id: editPublicId  })
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
  if(isEditing && editPublicId){
    M.toast({html:'Loading quiz data...'});
    fetch('load_quiz.php?id=' + encodeURIComponent(editPublicId))
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
        q.items.forEach(item => addItem(item));
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
