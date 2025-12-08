<footer class="tours-footer">
    <div class="footer-content container">
        <div class="row">
            
            <!-- Left Section -->
            <div class="col l6 s12 footer-section">
                <h5 class="footer-title">What's TOURS</h5>
                <p class="footer-text">
                    This platform is designed for practicing flight booking simulations while also serving
                    as an interactive learning environment.
                </p>
            </div>

            <!-- Right Section -->
            <div class="col l4 offset-l2 s12 footer-section">
                <h5 class="footer-title">Official Links</h5>
                <ul class="footer-links">
                    <li><a href="#" class="footer-link">URS Website</a></li>
                    <li><a href="#" class="footer-link">URS Official Page</a></li>
                    <li><a href="#" class="footer-link">Official Link</a></li>
                </ul>
            </div>

        </div>
    </div>

    <div class="footer-bottom">
        TOURS @ URSAC2025
    </div>
</footer>


<!-- STYLE -->
<style>
/* MAIN FOOTER WRAPPER */
.tours-footer {
    background: #0d47a1;
    background: linear-gradient(90deg, #0d47a1, #1565c0);
    color: #e3f2fd;
    padding-top: 35px;
    box-shadow: 0 -3px 8px rgba(0,0,0,0.2);
}

/* TEXT + TITLES */
.footer-title {
    font-weight: 700;
    margin-bottom: 12px;
    color: #fff;
}

.footer-text {
    font-size: 15px;
    line-height: 1.6;
}

/* LINKS */
.footer-links {
    list-style: none;
    padding-left: 0;
    margin: 0;
}

.footer-link {
    color: #bbdefb;
    text-decoration: none;
    display: block;
    padding: 4px 0;
    transition: color 0.2s ease;
}

.footer-link:hover {
    color: #ffffff;
}

/* BOTTOM BAR */
.footer-bottom {
    width: 100%;
    text-align: center;
    padding: 10px 0;
    margin-top: 30px;
    background: rgba(0,0,0,0.18);
    font-weight: 600;
    letter-spacing: 1px;
    color: #e3f2fd;
}

/* RESPONSIVE */
@media (max-width: 600px) {
    .footer-section { margin-bottom: 25px; }
}
</style>


<!-- LOGIN MODAL LOGIC (unchanged but cleaned a bit) -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const loginForm = document.getElementById('loginForm');
    if (!loginForm) return;

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
        e.preventDefault();
        clearErrors();

        const formData = new FormData(loginForm);

        // Validation
        if (!formData.get('acc_id').trim()) return errAccId.textContent = "Please enter Account ID.";
        if (!formData.get('password').trim()) return errPassword.textContent = "Please enter password.";

        try {
            const response = await fetch("login.php", {
                method: "POST",
                body: formData,
                headers: { "Accept": "application/json" },
                credentials: "same-origin"
            });

            const data = await response.json();

            if (data.success) {
                try {
                    let inst = M.Modal.getInstance(loginModalElem) || M.Modal.init(loginModalElem);
                    inst.close();
                } catch (err) {}

                window.location.reload();
                return;
            }

            if (data.errors?.acc_id) errAccId.textContent = data.errors.acc_id;
            if (data.errors?.password) errPassword.textContent = data.errors.password;
            if (data.errors?.general) errGeneral.textContent = data.errors.general;

        } catch (err) {
            errGeneral.textContent = "Network/server error.";
        }
    });
});
</script>
