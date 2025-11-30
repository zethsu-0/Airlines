<footer class="page-footer blue">
  <div class="footer-main container">
    <div class="row">
      <div class="col l6 s12">
        <h5>What's TOURS</h5>
        <p>This website is intended to be used as a place to practice flight booking while also serving as an interactive learning platform.</p>
      </div>
      <div class="col l4 offset-l2 s12">
        <h5>Official Links</h5>
        <ul class="footer-links">
          <li>URS Website</li>
          <li>URS Official Page </li>
          <li>Official Link</li>
        </ul>
      </div>
    </div>
  </div>

  <div class="footer-bottom center light-blue bold" style="padding: 5px;">
    TOURS @ URSAC2025
  </div>
</footer>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const loginForm = document.getElementById('loginForm');
    if (!loginForm) {
        console.error("Login form not found");
        return;
    }

    // Make sure Materialize modal exists
    const loginModalElem = document.getElementById('loginModal');
    const loginModalInstance = M.Modal.getInstance(loginModalElem) || M.Modal.init(loginModalElem);

    const errAccId = document.getElementById('err-acc_id');
    const errPassword = document.getElementById('err-password');
    const errGeneral = document.getElementById('err-general');

    function clearErrors() {
        errAccId.textContent = '';
        errPassword.textContent = '';
        errGeneral.textContent = '';
    }

    loginForm.addEventListener('submit', async function (e) {
        e.preventDefault();  // <<<< THIS PREVENTS NAVIGATION TO login.php
        clearErrors();

        const formData = new FormData(loginForm);

        const accIdVal = formData.get('acc_id').trim();
        const pwVal = formData.get('password').trim();

        if (!accIdVal) {
            errAccId.textContent = "Please enter Account ID.";
            return;
        }
        if (!pwVal) {
            errPassword.textContent = "Please enter password.";
            return;
        }

        try {
            const response = await fetch("login.php", {
                method: "POST",
                body: formData,
                headers: { "Accept": "application/json" },
                credentials: "same-origin"
            });

            const data = await response.json();

            if (data.success) {
              // Close modal (robust)
              try {
                const modalElem = document.getElementById('loginModal');
                if (modalElem && window.M) {
                  let inst = M.Modal.getInstance(modalElem) || M.Modal.init(modalElem);
                  if (inst && typeof inst.close === 'function') inst.close();
                }
              } catch (err) {
                console.warn('Modal close failed:', err);
              }

              // Force a full reload so PHP re-renders header with the new session
              // This prevents duplicate nav items and makes server-side checks see the logged-in user.
              window.location.reload();
              return;
            }

            // Show backend validation errors
            if (data.errors.acc_id) errAccId.textContent = data.errors.acc_id;
            if (data.errors.password) errPassword.textContent = data.errors.password;
            if (data.errors.general) errGeneral.textContent = data.errors.general;

        } catch (err) {
            console.error("Fetch error:", err);
            errGeneral.textContent = "Network/server error.";
        }
    });
});
</script>
