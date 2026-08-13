/* =========================================================
   AXION GLOBAL — Site behaviour
   Drives the hooks already present in the markup:
     .nav / #navBurger / #navMobile     → sticky + mobile menu
     [data-reveal]                       → reveal-on-scroll
     [data-tilt]                         → subtle card tilt
     [data-scroll-to]                    → smooth anchor scroll
     [data-open-modal] / [data-close-modal] / #inquiryForm
                                         → inquiry modal
   ========================================================= */

(function () {
  "use strict";

  var reduceMotion = window.matchMedia &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;


  /* -------------------------------------------------------
     1. Sticky nav
     ------------------------------------------------------- */

  var nav = document.getElementById("nav");

  if (nav) {
    var onScroll = function () {
      nav.classList.toggle("is-scrolled", window.scrollY > 24);
    };
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }


  /* -------------------------------------------------------
     2. Mobile menu
     ------------------------------------------------------- */

  var burger = document.getElementById("navBurger");
  var navMobile = document.getElementById("navMobile");

  if (burger && navMobile) {

    var setMenu = function (open) {
      navMobile.classList.toggle("is-open", open);
      burger.setAttribute("aria-expanded", open ? "true" : "false");
    };

    burger.addEventListener("click", function () {
      setMenu(!navMobile.classList.contains("is-open"));
    });

    /* Close after tapping a link */
    navMobile.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () { setMenu(false); });
    });

    /* Close when the viewport grows back to desktop */
    window.addEventListener("resize", function () {
      if (window.innerWidth > 860) setMenu(false);
    });
  }


  /* -------------------------------------------------------
     3. Reveal on scroll
     ------------------------------------------------------- */

  var revealTargets = document.querySelectorAll("[data-reveal]");

  if (!("IntersectionObserver" in window)) {
    /* No observer support — show everything rather than hide it */
    revealTargets.forEach(function (el) { el.classList.add("is-visible"); });
  } else {
    var revealObserver = new IntersectionObserver(function (entries, obs) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add("is-visible");
        obs.unobserve(entry.target);
      });
    }, { threshold: 0.12, rootMargin: "0px 0px -6% 0px" });

    revealTargets.forEach(function (el) { revealObserver.observe(el); });
  }


  /* -------------------------------------------------------
     4. Card tilt (home page spec cards / featured media)
     ------------------------------------------------------- */

  if (!reduceMotion && window.matchMedia("(hover: hover)").matches) {

    document.querySelectorAll("[data-tilt]").forEach(function (card) {

      card.style.transformStyle = "preserve-3d";

      card.addEventListener("mousemove", function (e) {
        var r = card.getBoundingClientRect();
        var px = (e.clientX - r.left) / r.width - 0.5;
        var py = (e.clientY - r.top) / r.height - 0.5;

        card.style.transform =
          "perspective(900px) rotateX(" + (-py * 4).toFixed(2) + "deg)" +
          " rotateY(" + (px * 4).toFixed(2) + "deg)";
      });

      card.addEventListener("mouseleave", function () {
        card.style.transform = "";
      });
    });
  }


  /* -------------------------------------------------------
     5. Smooth scroll for [data-scroll-to]
     ------------------------------------------------------- */

  document.querySelectorAll("[data-scroll-to]").forEach(function (el) {
    el.addEventListener("click", function () {
      var target = document.querySelector(el.getAttribute("data-scroll-to"));
      if (target) target.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  });


  /* -------------------------------------------------------
     6. Inquiry modal
     ------------------------------------------------------- */

  var modal = document.getElementById("modal");

  if (modal) {

    var modalTag     = document.getElementById("modalTag");
    var modalBgImg   = document.getElementById("modalBgImg");
    var serviceField = document.getElementById("modalServiceField");
    var form         = document.getElementById("inquiryForm");
    var formError    = document.getElementById("formError");
    var success      = document.getElementById("modalSuccess");

    /* Backdrop art per service context (data-bg) */
    var BG = {
      hero:         "https://images.pexels.com/photos/34118729/pexels-photo-34118729.jpeg?auto=compress&cs=tinysrgb&w=1200",
      lab:          "https://images.pexels.com/photos/1146627/pexels-photo-1146627.jpeg?auto=compress&cs=tinysrgb&w=1200",
      it:           "https://images.pexels.com/photos/37730212/pexels-photo-37730212.jpeg?auto=compress&cs=tinysrgb&w=1200",
      furniture:    "https://images.pexels.com/photos/33827312/pexels-photo-33827312.jpeg?auto=compress&cs=tinysrgb&w=1200",
      construction: "https://images.pexels.com/photos/14169558/pexels-photo-14169558.jpeg?auto=compress&cs=tinysrgb&w=1200"
    };

    var lastFocused = null;

    var openModal = function (trigger) {

      var service = trigger.getAttribute("data-service") || "";
      var bgKey   = trigger.getAttribute("data-bg") || "hero";

      if (modalTag) modalTag.textContent = service || "General Inquiry";
      if (serviceField) serviceField.value = service || "General Inquiry";
      if (modalBgImg) modalBgImg.src = BG[bgKey] || BG.hero;

      /* Always reopen on the form, not a stale success state */
      if (form) form.hidden = false;
      if (success) {
        success.hidden = true;
        success.classList.remove("is-visible");
      }
      if (formError) formError.hidden = true;

      lastFocused = document.activeElement;

      modal.classList.add("is-open");
      modal.setAttribute("aria-hidden", "false");
      document.body.style.overflow = "hidden";

      var firstInput = modal.querySelector("input:not([type=hidden]), textarea");
      if (firstInput) firstInput.focus();
    };

    var closeModal = function () {
      modal.classList.remove("is-open");
      modal.setAttribute("aria-hidden", "true");
      document.body.style.overflow = "";
      if (lastFocused && lastFocused.focus) lastFocused.focus();
    };

    document.querySelectorAll("[data-open-modal]").forEach(function (trigger) {
      trigger.addEventListener("click", function () { openModal(trigger); });
    });

    document.querySelectorAll("[data-close-modal]").forEach(function (el) {
      el.addEventListener("click", closeModal);
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && modal.classList.contains("is-open")) closeModal();
    });

    /* ---- Submission ---- */

    if (form) {

      var submitBtn = form.querySelector("[type=submit]");
      var submitText = submitBtn ? submitBtn.innerHTML : "";

      var showError = function (msg) {
        if (!formError) return;
        formError.textContent = msg;
        formError.hidden = false;
      };

      form.addEventListener("submit", function (e) {
        e.preventDefault();

        var missing = false;
        form.querySelectorAll("[required]").forEach(function (input) {
          if (!input.value.trim()) missing = true;
        });

        if (missing || !form.checkValidity()) {
          showError("Please fill in all required fields before submitting.");
          return;
        }

        if (formError) formError.hidden = true;

        var payload = {};
        new FormData(form).forEach(function (value, key) { payload[key] = value; });
        /* Pages are served without an extension, so this is "home",
           "xrd-services" and so on. The fallback covers "/" itself. */
        payload.page = location.pathname.split("/").pop() || "home";

        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.textContent = "Sending…";
        }

        var restore = function () {
          if (!submitBtn) return;
          submitBtn.disabled = false;
          submitBtn.innerHTML = submitText;
        };

        /* Rooted, not relative: extensionless URLs have no trailing
           slash, so a relative path would resolve differently
           depending on how the visitor arrived at the page. */
        fetch("/api/submit-inquiry.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload)
        })
          .then(function (res) {
            /* Must be real JSON. A 200 carrying anything else means the
               server never ran the PHP — treating that as success would
               show a thank-you while the lead is silently lost. */
            return res.text().then(function (body) {
              var data;
              try {
                data = JSON.parse(body);
              } catch (e) {
                throw new Error("The inquiry form is not configured on this server.");
              }
              return data;
            });
          })
          .then(function (data) {
            if (!data || !data.ok) {
              throw new Error((data && data.error) || "Something went wrong.");
            }

            restore();
            form.hidden = true;

            if (success) {
              success.hidden = false;
              /* next frame, so the animation actually plays */
              requestAnimationFrame(function () {
                success.classList.add("is-visible");
              });
            }

            form.reset();
          })
          .catch(function (err) {
            restore();
            showError(
              (err && err.message ? err.message : "We could not send your inquiry.") +
              " Please try again, or reach us on WhatsApp."
            );
          });
      });
    }
  }

})();
