(function () {
  "use strict";
  var storageKey = "pigiveaways-settings-active-tab";
  function tabs() {
    return Array.prototype.slice.call(
      document.querySelectorAll(".pit-tab-nav__item[data-tab]"),
    );
  }
  function show(slug, persist) {
    var all = tabs(),
      selected = all.find(function (tab) {
        return tab.dataset.tab === slug;
      });
    if (!selected) {
      selected = all[0];
      slug = selected ? selected.dataset.tab : "";
    }
    if (!selected) return;
    document
      .querySelectorAll(".pit-tab-panel[data-panel]")
      .forEach(function (panel) {
        var active = panel.dataset.panel === slug;
        panel.hidden = !active;
        panel.style.display = active ? "block" : "none";
      });
    all.forEach(function (tab) {
      var active = tab === selected;
      tab.classList.toggle("is-active", active);
      tab.setAttribute("aria-selected", active ? "true" : "false");
      tab.setAttribute("tabindex", active ? "0" : "-1");
    });
    var full =
      slug === "support" ||
      slug === "license" ||
      slug === "upgrade" ||
      slug === "pro" ||
      slug.indexOf("pro-") === 0;
    var shell = document.querySelector(".pit-two-col"),
      footer = document.getElementById("pit-save-footer");
    if (shell) shell.classList.toggle("is-full-width", full);
    if (footer)
      footer.style.display =
        ["support", "license", "upgrade", "pro"].indexOf(slug) >= 0 ||
        slug.indexOf("pro-") === 0
          ? "none"
          : "";
    if (persist !== false) {
      try {
        window.localStorage.setItem(storageKey, slug);
      } catch (error) {}
      if (window.history && window.history.replaceState)
        window.history.replaceState(null, "", "#pit-panel-" + slug);
    }
  }
  document.addEventListener("click", function (event) {
    var tab = event.target.closest(".pit-tab-nav__item[data-tab]");
    if (tab) {
      event.preventDefault();
      show(tab.dataset.tab, true);
      return;
    }
    var action = event.target.closest("a");
    if (
      action &&
      action.textContent.trim() === "Award winner" &&
      !window.confirm(
        "Assign the winner award to this valid entry? This is a manual administrative decision.",
      )
    )
      event.preventDefault();
  });
  document.addEventListener("keydown", function (event) {
    var tab = event.target.closest(".pit-tab-nav__item[data-tab]");
    if (
      !tab ||
      ["ArrowLeft", "ArrowRight", "Home", "End"].indexOf(event.key) < 0
    )
      return;
    event.preventDefault();
    var all = tabs(),
      index = all.indexOf(tab),
      next =
        event.key === "Home"
          ? 0
          : event.key === "End"
            ? all.length - 1
            : (index + (event.key === "ArrowRight" ? 1 : -1) + all.length) %
              all.length;
    all[next].focus();
    show(all[next].dataset.tab, true);
  });
  document.addEventListener("DOMContentLoaded", function () {
    var hash =
      window.location.hash.indexOf("#pit-panel-") === 0
        ? window.location.hash.replace("#pit-panel-", "")
        : "";
    var query = "";
    try {
      query = new URLSearchParams(window.location.search).get("tab") || "";
    } catch (error) {}
    var saved = "";
    try {
      saved = window.localStorage.getItem(storageKey) || "";
    } catch (error) {}
    show(hash || query || saved || "general", false);
  });
})();

(function () {
  "use strict";
  document.addEventListener("click", function (event) {
    var add = event.target.closest("[data-add-field]");
    if (add) {
      event.preventDefault();
      var builder = add.closest("[data-form-builder]"),
        list = builder && builder.querySelector("[data-form-fields]"),
        template = builder && builder.querySelector("[data-field-template]");
      if (!list || !template) return;
      var index = parseInt(list.dataset.nextIndex || "0", 10);
      list.insertAdjacentHTML(
        "beforeend",
        template.innerHTML.replace(/__INDEX__/g, String(index)),
      );
      list.dataset.nextIndex = String(index + 1);
      return;
    }
    var remove = event.target.closest(".pig-remove-field");
    if (remove) {
      event.preventDefault();
      var row = remove.closest(".pig-form-field-row");
      if (row) row.remove();
    }
  });
  document.addEventListener("change", function (event) {
    if (!event.target.matches(".pig-form-field-row select")) return;
    var row = event.target.closest(".pig-form-field-row"),
      options = row && row.querySelector(".pig-field-options");
    if (options)
      options.style.display = event.target.value === "select" ? "flex" : "none";
  });
  document.addEventListener("DOMContentLoaded", function () {
    document
      .querySelectorAll(".pig-form-field-row select")
      .forEach(function (select) {
        select.dispatchEvent(new Event("change", { bubbles: true }));
      });
  });
})();

(function () {
  "use strict";
  function showSub(button) {
    var nav = button.closest("[data-pitpro-subnav-group]"),
      group = nav && nav.dataset.pitproSubnavGroup,
      section = button.dataset.pitproSubnavTab;
    if (!group) return;
    nav.querySelectorAll("[data-pitpro-subnav-tab]").forEach(function (tab) {
      var active = tab === button;
      tab.classList.toggle("is-active", active);
      tab.setAttribute("aria-selected", active ? "true" : "false");
    });
    document
      .querySelectorAll(
        '[data-pitpro-subnav-panel][data-pitpro-subnav-group="' + group + '"]',
      )
      .forEach(function (panel) {
        panel.hidden = panel.dataset.pitproSubnavPanel !== section;
      });
  }
  document.addEventListener("click", function (event) {
    var move = event.target.closest("[data-move-field]");
    if (move) {
      event.preventDefault();
      var row = move.closest("[data-field-row]"),
        direction = move.dataset.moveField;
      if (!row) return;
      if (direction === "up" && row.previousElementSibling)
        row.parentNode.insertBefore(row, row.previousElementSibling);
      if (direction === "down" && row.nextElementSibling)
        row.parentNode.insertBefore(row.nextElementSibling, row);
      return;
    }
    var sub = event.target.closest("[data-pitpro-subnav-tab]");
    if (sub) {
      event.preventDefault();
      showSub(sub);
    }
  });
  document.addEventListener("DOMContentLoaded", function () {
    document
      .querySelectorAll("[data-pitpro-subnav-group]")
      .forEach(function (nav) {
        var active =
          nav.querySelector("[data-pitpro-subnav-tab].is-active") ||
          nav.querySelector("[data-pitpro-subnav-tab]");
        if (active) showSub(active);
      });
  });
})();

(function () {
  "use strict";
  function update() {
    var preview = document.querySelector("[data-pig-listing-preview]");
    if (!preview) return;
    var values = {};
    document
      .querySelectorAll("[data-preview-setting]")
      .forEach(function (control) {
        values[control.dataset.previewSetting] =
          control.type === "checkbox" ? control.checked : control.value;
      });
    preview.className =
      "pig-listing-preview pig-listing-preview--" +
      (values.layout || "grid") +
      " pig-listing-preview--" +
      (values.card || "bordered") +
      " pig-listing-preview--" +
      (values.ratio || "landscape");
    preview.style.setProperty("--pig-preview-columns", values.columns || "3");
    preview.querySelectorAll(".pig-preview-excerpt").forEach(function (el) {
      el.hidden = !values.excerpt;
    });
    preview.querySelectorAll(".pig-preview-status").forEach(function (el) {
      el.hidden = !values.status;
    });
    preview.querySelectorAll(".pig-preview-details").forEach(function (el) {
      el.hidden = !values.prize;
    });
    preview.querySelectorAll(".pig-preview-body h3").forEach(function (title) {
      title.style.fontWeight = values.titleWeight || "800";
      title.style.fontSize = (values.titleSize || "20") + "px";
      title.style.color = values.titleColor || "#1d2327";
    });
  }
  document.addEventListener("input", function (event) {
    if (event.target.matches("[data-preview-setting]")) update();
  });
  document.addEventListener("change", function (event) {
    if (event.target.matches("[data-preview-setting]")) update();
  });
  document.addEventListener("DOMContentLoaded", update);
})();

(function () {
  "use strict";
  function activeTab() {
    var tab = document.querySelector(".pit-tab-nav__item.is-active[data-tab]");
    return tab ? tab.dataset.tab : "general";
  }
  function panels() {
    var slug = activeTab();
    document
      .querySelectorAll("[data-pig-preview-panel]")
      .forEach(function (panel) {
        var target = panel.dataset.pigPreviewPanel;
        panel.hidden =
          target !==
          (slug === "form" ? "form" : slug === "design" ? "design" : "default");
      });
  }
  function formPreview() {
    var preview = document.querySelector("[data-pig-form-preview]");
    if (!preview) return;
    preview.textContent = "";
    var layout = document.querySelector("[data-pig-form-layout]");
    preview.classList.toggle("is-one-column", layout && layout.value === "one");
    document
      .querySelectorAll("#pit-panel-form .pig-form-field-row")
      .forEach(function (row) {
        var labelInput = row.querySelector('input[name$="[label]"]'),
          typeInput = row.querySelector('select[name$="[type]"]'),
          requiredInput = row.querySelector(
            '.pig-required input[type="checkbox"]',
          ),
          visibleInput = row.querySelector(
            '.pig-field-visible input[type="checkbox"]',
          );
        if (visibleInput && !visibleInput.checked) return;
        if (!labelInput || !labelInput.value) return;
        var field = document.createElement("label");
        field.className = "pig-form-preview-field";
        var caption = document.createElement("span");
        caption.textContent =
          labelInput.value +
          (requiredInput && requiredInput.checked ? " *" : "");
        field.appendChild(caption);
        var type = typeInput ? typeInput.value : "text",
          control;
        if (type === "textarea") control = document.createElement("textarea");
        else if (type === "select") control = document.createElement("select");
        else {
          control = document.createElement("input");
          control.type = type === "checkbox" ? "checkbox" : type;
        }
        control.disabled = true;
        if (type === "checkbox") field.classList.add("is-checkbox");
        field.appendChild(control);
        preview.appendChild(field);
      });
    var button = document.createElement("button");
    button.type = "button";
    button.disabled = true;
    button.textContent = "Submit Entry";
    preview.appendChild(button);
  }
  function later() {
    window.setTimeout(function () {
      panels();
      formPreview();
    }, 0);
  }
  document.addEventListener("click", function (event) {
    if (
      event.target.closest(
        ".pit-tab-nav__item[data-tab], [data-add-field], .pig-remove-field, [data-move-field]",
      )
    )
      later();
  });
  document.addEventListener("keydown", function (event) {
    if (event.target.closest(".pit-tab-nav__item[data-tab]")) later();
  });
  document.addEventListener("input", function (event) {
    if (event.target.closest('[data-panel="form"]')) formPreview();
  });
  document.addEventListener("change", function (event) {
    if (event.target.closest('[data-panel="form"]')) formPreview();
  });
  document.addEventListener("DOMContentLoaded", later);
})();

(function () {
  "use strict";
  document.addEventListener("DOMContentLoaded", function () {
    var config = window.pigiveawaysRecaptchaTest,
      panel = document.getElementById("pit-panel-privacy");
    if (!config || !panel) return;
    var threshold = panel.querySelector('input[name$="[recaptcha_threshold]"]'),
      anchor = threshold && threshold.closest("label");
    if (!anchor) return;
    var instructions = panel.querySelector("ol li:last-child");
    if (instructions)
      instructions.textContent =
        "Enable protection, save the settings, then use Test reCAPTCHA Configuration below.";
    var box = document.createElement("div");
    box.className = "pig-recaptcha-test";
    box.innerHTML =
      '<button type="button" class="button" data-pig-test-recaptcha>Test reCAPTCHA Configuration</button><span data-pig-recaptcha-test-result role="status" aria-live="polite"></span>';
    anchor.insertAdjacentElement("afterend", box);
    var button = box.querySelector("button"),
      result = box.querySelector("[data-pig-recaptcha-test-result]");
    function finish(ok, message) {
      button.disabled = false;
      button.textContent = "Test reCAPTCHA Configuration";
      result.className = ok ? "is-success" : "is-error";
      result.textContent = message;
    }
    button.addEventListener("click", function () {
      if (!config.configured || !config.siteKey) {
        finish(
          false,
          "Save a valid site key and secret key before running the test.",
        );
        return;
      }
      button.disabled = true;
      button.textContent = config.testing || "Testing…";
      result.className = "";
      result.textContent = "";
      var done = false,
        timer = window.setTimeout(function () {
          if (done) return;
          done = true;
          finish(false, config.error);
        }, 15000);
      function fail(message) {
        if (done) return;
        done = true;
        window.clearTimeout(timer);
        finish(false, message || config.error);
      }
      try {
        if (!window.grecaptcha || typeof grecaptcha.ready !== "function") {
          fail(config.error);
          return;
        }
        grecaptcha.ready(function () {
          grecaptcha
            .execute(config.siteKey, { action: "giveaway_configuration_test" })
            .then(function (token) {
              var body = new URLSearchParams({
                action: "pigiveaways_test_recaptcha",
                nonce: config.nonce,
                token: token,
              });
              return fetch(config.ajaxUrl, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                  "Content-Type":
                    "application/x-www-form-urlencoded; charset=UTF-8",
                },
                body: body.toString(),
              });
            })
            .then(function (response) {
              return response.json();
            })
            .then(function (payload) {
              if (done) return;
              done = true;
              window.clearTimeout(timer);
              finish(
                !!payload.success,
                payload.data && payload.data.message
                  ? payload.data.message
                  : config.error,
              );
            })
            .catch(function () {
              fail(config.error);
            });
        });
      } catch (error) {
        fail(config.error);
      }
    });
  });
})();
