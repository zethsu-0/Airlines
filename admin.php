<!DOCTYPE html>
<html>
<head>
	<?php include('templates/header.php'); ?>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	  <link rel="stylesheet" href="css/admin.css">
	<title>Admin</title>
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
        <div style="width:100%; height:140px; display:flex; justify-content:center; align-items:center;">
          <!-- small placeholder chart (user can replace with image or chart lib) -->
          <img src="/assets/pie-sample.png" alt="pie" style="max-width:140px;">
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

	    document.addEventListener('DOMContentLoaded', function() {
        var modals = document.querySelectorAll('.modal');
        M.Modal.init(modals);
    });
</script>

<?php include('templates/footer.php'); ?>
</body>


</html>aa
