(function () {
  "use strict";
  function state(form, busy, message) {
    var button = form.querySelector(".pig-submit");
    if (button) {
      if (!button.dataset.pigOriginalLabel)
        button.dataset.pigOriginalLabel = button.textContent;
      button.disabled = busy;
      button.textContent = busy
        ? (window.pigiveawaysRecaptcha &&
            window.pigiveawaysRecaptcha.submitting) ||
          "Submitting…"
        : button.dataset.pigOriginalLabel;
    }
    var notice = form.querySelector("[data-pig-recaptcha-error]");
    if (message) {
      if (!notice) {
        notice = document.createElement("div");
        notice.className = "pig-notice pig-error";
        notice.setAttribute("data-pig-recaptcha-error", "");
        notice.setAttribute("role", "alert");
        form.insertBefore(notice, button || form.firstChild);
      }
      notice.textContent = message;
    } else if (notice) notice.remove();
  }
  document.addEventListener("submit", function (event) {
    var form = event.target;
    if (!form.matches(".pig-entry-form") || form.dataset.pigCaptchaReady)
      return;
    var config = window.pigiveawaysRecaptcha;
    if (!config || !config.siteKey) {
      state(form, true, "");
      return;
    }
    event.preventDefault();
    state(form, true, "");
    var finished = false;
    function fail() {
      if (finished) return;
      finished = true;
      state(
        form,
        false,
        config.error ||
          "reCAPTCHA could not verify your entry. Please try again.",
      );
    }
    var timer = window.setTimeout(fail, 12000);
    try {
      if (!window.grecaptcha || typeof window.grecaptcha.ready !== "function") {
        window.clearTimeout(timer);
        fail();
        return;
      }
      window.grecaptcha.ready(function () {
        try {
          window.grecaptcha
            .execute(config.siteKey, {
              action: config.action || "giveaway_entry",
            })
            .then(function (token) {
              if (finished) return;
              window.clearTimeout(timer);
              if (!token) {
                fail();
                return;
              }
              finished = true;
              var input = form.querySelector('[name="g-recaptcha-response"]');
              if (input) input.value = token;
              form.dataset.pigCaptchaReady = "1";
              form.submit();
            })
            .catch(function () {
              window.clearTimeout(timer);
              fail();
            });
        } catch (error) {
          window.clearTimeout(timer);
          fail();
        }
      });
    } catch (error) {
      window.clearTimeout(timer);
      fail();
    }
  });
})();
(function () {
  "use strict";
  function update() {
    document.querySelectorAll("[data-pig-countdown]").forEach(function (el) {
      var remaining = parseInt(el.dataset.end || "0", 10) * 1000 - Date.now();
      if (remaining <= 0) {
        el.textContent = "Closed";
        return;
      }
      var seconds = Math.floor(remaining / 1000),
        days = Math.floor(seconds / 86400);
      seconds %= 86400;
      var hours = Math.floor(seconds / 3600);
      seconds %= 3600;
      var minutes = Math.floor(seconds / 60),
        secs = seconds % 60;
      el.textContent =
        "Closes in " +
        (days ? days + "d " : "") +
        String(hours).padStart(2, "0") +
        "h " +
        String(minutes).padStart(2, "0") +
        "m " +
        String(secs).padStart(2, "0") +
        "s";
    });
  }
  document.addEventListener("DOMContentLoaded", function () {
    update();
    window.setInterval(update, 1000);
  });
})();
(function () {
  "use strict";
  function ready() {
    var modal = document.querySelector("[data-pig-outcome-modal]");
    if (!modal) return;
    document.documentElement.classList.add("pig-outcome-open");
    var closed = false;
    function close() {
      if (closed) return;
      closed = true;
      document.documentElement.classList.remove("pig-outcome-open");
      modal.remove();
      document.dispatchEvent(new CustomEvent("pig:outcome-closed"));
    }
    modal.addEventListener("click", function (event) {
      if (event.target.closest("[data-pig-outcome-close]")) {
        event.preventDefault();
        close();
      }
    });
    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && !closed) close();
    });
    var button = modal.querySelector(".pig-outcome-done");
    if (button) button.focus();
  }
  document.readyState === "loading"
    ? document.addEventListener("DOMContentLoaded", ready)
    : ready();
})();
