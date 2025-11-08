<footer class="page-footer blue lighten-3">
  <div class="container black-text">
    <div class="row">
      <div class="col l6 s12">
        <h5>Travel More Ewan</h5>
        <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
      </div>
      <div class="col l4 offset-l2 s12">
        <h5>Official Links</h5>
        <ul class="footer-links">
          <li>TOURS TRAVELS</li>
          <li>CURRENCY EXCHANGE</li>
          <li>HOTELS</li>
        </ul>
      </div>
    </div>
  </div>

  <div class="footer-copyright">
    <div class="container center black-text">TOURS @ URSAC2025</div>
  </div>


<style>
  html, body { margin: 0; padding: 0; min-height: 100vh; background-color: gray; }
  footer.page-footer { margin: 0; padding: 0; border: none; }
  .footer-links { margin: 0; padding-left: 0; list-style: none; }


  .hero-carousel {
    position: relative;
    background: url('assets/island.jpg') center/cover fixed no-repeat;
    padding: 80px 0;
  }
  .hero-carousel .overlay-bg {
    background: rgba(0, 0, 0, 0.45);
    padding: 40px 0;
  }


  .destination-card {
    position: relative;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    cursor: pointer;
    transition: transform 0.3s ease;
    width: 90%;
    max-width: 600px;
    margin: 0 auto;
  }

  .destination-card img {
    width: 100%;
    height: 220px;
    object-fit: cover;
    display: block;
    border-radius: 15px;
  }


  .country-label {
    position: absolute;
    bottom: 10px;
    left: 10px;
    background: rgba(0, 0, 0, 0.55);
    color: white;
    padding: 5px 10px;
    border-radius: 10px;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 1rem;
  }


  .card-reveal-overlay {
    position: absolute;
    inset: 0;
    background: rgba(20, 20, 20, 0.85);
    color: white;
    opacity: 0;
    visibility: hidden;
    transform: translateY(20px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 15px;
    text-align: center;
    transition: opacity 0.4s ease, transform 0.4s ease;
  }
  .card-reveal-overlay.active {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
  }

  .reveal-content { max-width: 90%; }
  .reveal-content .card-title {
    font-size: 1.2rem;
    font-weight: 700;
    display: flex;
    justify-content: space-between;
  }
  .close-reveal { cursor: pointer; color: #fff; }


  @media (max-width: 768px) {
    .destination-card img { height: 130px; }
    .country-label { font-size: 0.9rem; }
  }
  @media (max-width: 480px) {
    .destination-card img { height: 110px; }
    .country-label { font-size: 0.8rem; bottom: 6px; left: 6px; }
  }

  label {
    font-size: 200px;
    font-weight: bold;
    color: black;
    letter-spacing: 5px;
  }
  .btn {
    font-weight: bold;
    font-size: 20px;
    color: black;
    background-color: transparent;
  }
  .submitbtn { padding-top: 20px !important; }
  h2, h3 { font-weight: bold; }
  input[type="text"] { text-transform: uppercase; }
  .bg-container { padding: 20px; width: 90%; }

  .flatpickr-current-month {
    position: relative; z-index: 10;
    display: flex !important; align-items: center; justify-content: center; gap: 0.3rem;
  }
  select.flatpickr-monthDropdown-months {
    display: inline-block !important; position: relative; z-index: 11;
    background: transparent; color: #1976d2; font-weight: bold;
    border: none; font-size: 1rem; text-transform: capitalize;
  }
  .flatpickr-current-month .numInputWrapper {
    display: inline-flex !important; align-items: center; position: relative;
    z-index: 11; background: transparent; margin-left: 0.2rem; width: 9ch;
  }
  .flatpickr-current-month input.cur-year {
    display: inline-block !important; color: #1976d2;
    font-weight: bold; border: none; background: transparent; text-align: center;
  }
  .flatpickr-innerContainer { position: relative; z-index: 1; }
  .btn:hover, .btn-large:hover, .btn-small:hover {
    background-color: #4993deff;
  }
</style>


<script>
  document.addEventListener('DOMContentLoaded', function() {
  
    const carouselElems = document.querySelectorAll('.carousel');
    M.Carousel.init(carouselElems, { indicators: false, dist: -50, padding: 20 });

    const carouselElem = document.querySelector('.carousel');
    if (!carouselElem) return;
    const instance = M.Carousel.getInstance(carouselElem);
    const items = [...carouselElem.querySelectorAll('.carousel-item')];
    const cards = [...carouselElem.querySelectorAll('.destination-card')];

    const hideAll = () => document.querySelectorAll('.card-reveal-overlay.active')
      .forEach(o => o.classList.remove('active'));


    cards.forEach((card, index) => {
      const overlay = card.querySelector('.card-reveal-overlay');
      const closeBtn = card.querySelector('.close-reveal');

      card.querySelector('img')?.addEventListener('click', e => {
        e.stopPropagation();
        hideAll();
        instance.set(index);
        setTimeout(() => overlay?.classList.add('active'), 400);
      });

      closeBtn?.addEventListener('click', e => {
        e.stopPropagation();
        overlay?.classList.remove('active');
      });
    });


    let last = -1;
    setInterval(() => {
      const cr = carouselElem.getBoundingClientRect(), cx = cr.left + cr.width / 2;
      const idx = items.reduce((b, it, i) => {
        const r = it.getBoundingClientRect(), d = Math.abs((r.left + r.width / 2) - cx);
        return d < b[0] ? [d, i] : b;
      }, [Infinity, 0])[1];
      if (idx !== last) { hideAll(); last = idx; }
    }, 150);


    ['touchstart', 'mousedown', 'click'].forEach(evt => {
      carouselElem.addEventListener(evt, e => {
        if (!e.target.closest('.destination-card')) hideAll();
      }, { passive: true });
    });


    if (typeof flatpickr !== "undefined") {
      flatpickr("#flight-date", {
        dateFormat: "Y-m-d",
        altFormat: "F j",
        minDate: "today",
        allowInput: false,
        onReady: function() {
          M.updateTextFields(); 
        }
      });
    }
  });
</script>

</footer>
