<!DOCTYPE html>
<html>
<head>
  <?php include('templates/header_admin.php'); ?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="css/admin.css">
  <title>Admin</title>
  <!-- Chart.js CDN -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="page-wrap">
  <div class="layout container">

    <!-- LEFT: Create Button -->
    <div class="left-col">
      <a class="left-create modal-trigger" href="#createExamModal">CREATE QUIZ</a>
    </div>

    <!-- MIDDLE: Quizzes list -->
    <div>
      <div class="quiz-list">
        <!-- quiz cards populated by JS -->
        <div id="quizzesContainer"></div>
      </div>
    </div>

    <!-- RIGHT: Stats + Edit Students -->
    <div>
      <div style="display:flex; justify-content:flex-end; margin-bottom:18px;">
        <a href="Students.php" class="edit-students-btn">EDIT STUDENTS</a>
      </div>

      <div class="stats-box">
        <!-- Replaced placeholder with a canvas for Chart.js -->
        <div style="width:100%; height:140px; display:flex; justify-content:center; align-items:center;">
          <canvas id="quizPieChart" style="max-width:140px; max-height:140px;"></canvas>
        </div>

        <div style="margin-top: 18px;">
          <h5 style="font-weight:700;">STUDENTS WHO<br>SUBMITTED</h5>
        </div>

        <div style="writing-mode: vertical-rl; transform: rotate(180deg); margin-left: auto; margin-top: 12px; font-weight:800;">
          OTHER STATS
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Create Exam Modal -->
<div id="createExamModal" class="modal">
  <div class="modal-content">
    <h5>Create Quiz</h5>
    <div class="input-field">
      <input id="newQuizName" type="text">
      <label for="newQuizName">Exam/Quiz Name</label>
    </div>
    <div class="input-field">
      <input id="newQuizDeadline" type="date">
      <label for="newQuizDeadline">Deadline</label>
    </div>
    <a class="btn green" id="createQuizBtn">Create & Open</a>
  </div>
</div>

<!-- Edit Quiz Modal -->
<div id="editQuizModal" class="modal">
  <div class="modal-content">
    <h5>Edit Quiz</h5>
    <input type="hidden" id="editQuizIndex">
    <div class="input-field">
      <input id="editQuizName" type="text">
      <label for="editQuizName">Exam/Quiz Name</label>
    </div>
    <div class="input-field">
      <input id="editQuizDeadline" type="date">
      <label for="editQuizDeadline">Deadline</label>
    </div>
    <a class="btn blue" id="saveQuizBtn">Save</a>
  </div>
</div>

<!-- Materialize & app JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  M.Modal.init(document.querySelectorAll('.modal'));

  // load quizzes from localStorage or sample
  let quizzes = JSON.parse(localStorage.getItem('quizzes_v1')) || [
    {name:'EXAM/QUIZ NAME:', deadline:''},
    {name:'EXAM/QUIZ NAME:', deadline:''},
    {name:'EXAM/QUIZ NAME:', deadline:''}
  ];

  const quizzesContainer = document.getElementById('quizzesContainer');

  function renderQuizzes(){
    quizzesContainer.innerHTML = '';
    quizzes.forEach((q, idx) => {
      const wrapper = document.createElement('div');
      wrapper.className = 'frame-card';
      wrapper.style.position = 'relative';
      wrapper.innerHTML = `
        <div class="inner">
          <div style="flex:1">
            <div class="quiz-title">${q.name}</div>
            <div class="quiz-dead">DEAD LINE: ${q.deadline || '—'}</div>
          </div>
        </div>
        <div class="edit-circle" data-idx="${idx}">
          <i class="material-icons">edit</i>
        </div>
      `;
      // clicking edit circle opens modal and fills values
      wrapper.querySelector('.edit-circle').addEventListener('click', () => {
        document.getElementById('editQuizIndex').value = idx;
        document.getElementById('editQuizName').value = q.name;
        document.getElementById('editQuizDeadline').value = q.deadline || '';
        M.updateTextFields(); // update labels
        const modal = M.Modal.getInstance(document.getElementById('editQuizModal'));
        modal.open();
      });

      quizzesContainer.appendChild(wrapper);
    });
    // update pie chart after rendering
    updatePieChart();
  }

  renderQuizzes();

  // Create new quiz button -> add and open Exam.php
  document.getElementById('createQuizBtn').addEventListener('click', () => {
    const name = document.getElementById('newQuizName').value || 'EXAM/QUIZ NAME:';
    const deadline = document.getElementById('newQuizDeadline').value || '';
    quizzes.push({name, deadline});
    localStorage.setItem('quizzes_v1', JSON.stringify(quizzes));
    renderQuizzes();
    // open Exam.php (user said create -> open Exam page). Pass index via query param
    const idx = quizzes.length - 1;
    window.location.href = `Exam.php?q=${idx}`;
  });

  // Save edit
  document.getElementById('saveQuizBtn').addEventListener('click', () => {
    const idx = parseInt(document.getElementById('editQuizIndex').value,10);
    quizzes[idx].name = document.getElementById('editQuizName').value || 'EXAM/QUIZ NAME:';
    quizzes[idx].deadline = document.getElementById('editQuizDeadline').value || '';
    localStorage.setItem('quizzes_v1', JSON.stringify(quizzes));
    renderQuizzes();
    M.Modal.getInstance(document.getElementById('editQuizModal')).close();
  });

});

// init Materialize in case of multiple DOMContentLoaded listeners
document.addEventListener('DOMContentLoaded', function() {
  var modals = document.querySelectorAll('.modal');
  M.Modal.init(modals);
});

// ---------------------- Pie chart code ----------------------
// This uses Chart.js and will display a small pie chart inside the stats box.
// Data source: for demo it tries to read a 'submissions_v1' object from localStorage
// Format (optional): { totalStudents: 30, submitted: 12 }

function getSubmissionData(){
  try{
    const raw = localStorage.getItem('submissions_v1');
    if(raw){
      const obj = JSON.parse(raw);
      const total = parseInt(obj.totalStudents,10) || 30;
      const submitted = Math.max(0, Math.min(total, parseInt(obj.submitted,10) || 0));
      return { total, submitted };
    }
  }catch(e){/* ignore parse errors */}
  // fallback demo values
  return { total: 30, submitted: 12 };
}

let pieChart = null;
function updatePieChart(){
  const data = getSubmissionData();
  const submitted = data.submitted;
  const notSubmitted = Math.max(0, data.total - submitted);

  const ctx = document.getElementById('quizPieChart').getContext('2d');

  const chartData = {
    labels: ['Submitted', 'Not submitted'],
    datasets: [{
      data: [submitted, notSubmitted],
      // Chart.js will assign default colors; you can configure them if you want.
    }]
  };

  const config = {
    type: 'pie',
    data: chartData,
    options: {
      maintainAspectRatio: true,
      plugins: {
        legend: { display: false },
        tooltip: { enabled: true }
      }
    }
  };

  // destroy existing instance if present
  if(pieChart){ pieChart.destroy(); }
  pieChart = new Chart(ctx, config);
}

// call updatePieChart once the page is ready (in case DOM was parsed earlier)
window.addEventListener('load', () => {
  // small timeout to ensure canvas sizing is ready in some layouts
  setTimeout(updatePieChart, 50);
});

</script>

<?php include('templates/footer.php'); ?>
</body>


</html>
