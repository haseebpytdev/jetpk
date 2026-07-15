(function () {
  "use strict";

  function debounce(fn, wait) {
    var timer = null;
    return function () {
      var args = arguments;
      var ctx = this;
      window.clearTimeout(timer);
      timer = window.setTimeout(function () {
        fn.apply(ctx, args);
      }, wait);
    };
  }

  function PublicFormValidation(form, options) {
    this.form = form;
    this.options = options || {};
    this.endpoint = this.options.endpoint || "";
    this.csrf = this.options.csrf || "";
    this.fieldSelectors = this.options.fieldSelectors || {};
    this.requiredFields = this.options.requiredFields || [];
    this.pairedFields = this.options.pairedFields || {};
    this.passwordMismatchMessage = this.options.passwordMismatchMessage || "Password doesn't match.";
    this.mobileDigitsMessage = this.options.mobileDigitsMessage || "Only numbers are allowed. Do not use spaces, dashes, brackets, or special characters.";
    this.pending = {};
    this.validState = {};
    this.lastPayloadKey = {};
    this.throttleMessage = "Too many validation attempts. Please wait a moment and try again.";
    this.submitButton = form.querySelector('[type="submit"]');
    this.globalError = form.querySelector("[data-global-error]");
    this.fieldToPairKey = {};

    var self = this;
    Object.keys(this.pairedFields).forEach(function (pairKey) {
      self.pairedFields[pairKey].forEach(function (field) {
        self.fieldToPairKey[field] = pairKey;
      });
    });
  }

  PublicFormValidation.prototype.setGlobalError = function (message) {
    if (!this.globalError) return;
    if (message) {
      this.globalError.textContent = message;
      this.globalError.hidden = false;
      return;
    }
    this.globalError.hidden = true;
    this.globalError.textContent = "";
  };

  PublicFormValidation.prototype.fieldErrorNode = function (field) {
    return this.form.querySelector('[data-error-for="' + field + '"]');
  };

  PublicFormValidation.prototype.fieldInputNode = function (field) {
    return this.form.querySelector('[name="' + field + '"]') || this.form.querySelector('[name="' + (this.fieldSelectors[field] || "") + '"]');
  };

  PublicFormValidation.prototype.clearFieldState = function (field) {
    var input = this.fieldInputNode(field);
    var error = this.fieldErrorNode(field);
    if (error) error.textContent = "";
    if (input) {
      input.classList.remove("input-error");
      input.classList.remove("input-valid");
    }
  };

  PublicFormValidation.prototype.clearPairState = function (pairKey) {
    var fields = this.pairedFields[pairKey] || [];
    fields.forEach(function (field) {
      this.clearFieldState(field);
      this.validState[field] = false;
    }, this);
  };

  PublicFormValidation.prototype.isPasswordMismatchMessage = function (message) {
    if (!message) return false;
    var lower = String(message).toLowerCase();
    return lower.indexOf("match") !== -1 || lower.indexOf("same") !== -1 || lower.indexOf("confirmation") !== -1;
  };

  PublicFormValidation.prototype.resolveFieldMessage = function (field, errors) {
    var messages = (errors && errors[field]) || [];
    return messages.length ? messages[0] : "";
  };

  PublicFormValidation.prototype.applyFieldError = function (field, message) {
    var displayField = field;
    var text = message || "";

    if (field === "password" && this.isPasswordMismatchMessage(text)) {
      displayField = "password_confirmation";
      text = this.passwordMismatchMessage;
    }

    if (field === "mobile" && text && text.indexOf("numbers") !== -1) {
      text = this.mobileDigitsMessage;
    }

    var input = this.fieldInputNode(field);
    var error = this.fieldErrorNode(displayField);
    if (error) error.textContent = text;
    if (input) {
      input.classList.add("input-error");
      input.classList.remove("input-valid");
    }
    this.validState[field] = false;
    if (displayField !== field) {
      this.validState[displayField] = false;
    }
  };

  PublicFormValidation.prototype.applyFieldValid = function (field) {
    var input = this.fieldInputNode(field);
    if (input) {
      input.classList.remove("input-error");
      input.classList.add("input-valid");
    }
    this.validState[field] = true;
  };

  PublicFormValidation.prototype.getPairKey = function (field) {
    return this.fieldToPairKey[field] || null;
  };

  PublicFormValidation.prototype.getPairFields = function (field) {
    var pairKey = this.getPairKey(field);
    if (!pairKey) return [field];
    return this.pairedFields[pairKey] || [field];
  };

  PublicFormValidation.prototype.isPasswordPair = function (field) {
    return this.getPairKey(field) === "password";
  };

  PublicFormValidation.prototype.isMobilePair = function (field) {
    return this.getPairKey(field) === "mobile";
  };

  PublicFormValidation.prototype.payloadKey = function (field) {
    return JSON.stringify(this.serializePayload(field));
  };

  PublicFormValidation.prototype.clearPayloadCacheForField = function (field) {
    this.lastPayloadKey[field] = "";
    var pairKey = this.getPairKey(field);
    if (pairKey) {
      var fields = this.pairedFields[pairKey] || [];
      fields.forEach(function (name) {
        this.lastPayloadKey[name] = "";
      }, this);
    }
  };

  PublicFormValidation.prototype.handleThrottleResponse = function () {
    this.setGlobalError(this.throttleMessage);
    return false;
  };

  PublicFormValidation.prototype.clearPayloadCache = function (field) {
    this.clearPayloadCacheForField(field);
  };

  PublicFormValidation.prototype.serializePayload = function (field) {
    var payload = {
      field: field
    };
    for (var i = 0; i < this.requiredFields.length; i++) {
      var name = this.requiredFields[i];
      var sourceName = this.fieldSelectors[name] || name;
      var input = this.form.querySelector('[name="' + sourceName + '"]');
      payload[name] = input ? input.value : "";
    }
    return payload;
  };

  PublicFormValidation.prototype.validatePasswordPairLocally = function () {
    var passwordInput = this.fieldInputNode("password");
    var confirmInput = this.fieldInputNode("password_confirmation");
    if (!passwordInput || !confirmInput) return false;

    var password = passwordInput.value || "";
    var confirm = confirmInput.value || "";

    this.clearFieldState("password");
    this.clearFieldState("password_confirmation");

    if (!password && !confirm) {
      this.validState.password = false;
      this.validState.password_confirmation = false;
      return false;
    }

    if (!password || !confirm) {
      this.validState.password = false;
      this.validState.password_confirmation = false;
      return false;
    }

    if (password !== confirm) {
      this.applyFieldError("password_confirmation", this.passwordMismatchMessage);
      return false;
    }

    return true;
  };

  PublicFormValidation.prototype.applyPasswordPairServerErrors = function (errors) {
    var passwordMessage = this.resolveFieldMessage("password", errors);
    var confirmMessage = this.resolveFieldMessage("password_confirmation", errors);

    if (this.isPasswordMismatchMessage(passwordMessage) || this.isPasswordMismatchMessage(confirmMessage)) {
      this.applyFieldError("password_confirmation", this.passwordMismatchMessage);
      return;
    }

    if (passwordMessage) {
      this.applyFieldError("password", passwordMessage);
    }
    if (confirmMessage) {
      this.applyFieldError("password_confirmation", confirmMessage);
    }
    if (!passwordMessage && !confirmMessage) {
      this.applyFieldError("password_confirmation", this.passwordMismatchMessage);
    }
  };

  PublicFormValidation.prototype.applyMobilePairServerErrors = function (errors) {
    var codeMessage = this.resolveFieldMessage("mobile_country_code", errors);
    var mobileMessage = this.resolveFieldMessage("mobile", errors);

    if (mobileMessage && (mobileMessage.indexOf("numbers") !== -1 || mobileMessage.indexOf("regex") !== -1)) {
      mobileMessage = this.mobileDigitsMessage;
    }

    if (codeMessage) {
      this.applyFieldError("mobile_country_code", codeMessage);
    }
    if (mobileMessage) {
      this.applyFieldError("mobile", mobileMessage);
    }
    if (!codeMessage && !mobileMessage) {
      this.applyFieldError("mobile", this.mobileDigitsMessage);
    }
  };

  PublicFormValidation.prototype.validateField = async function (field, options) {
    options = options || {};
    if (!this.endpoint) return true;

    if (this.isPasswordPair(field)) {
      return this.validatePasswordPair(options);
    }

    var pairKey = this.getPairKey(field);
    if (pairKey && pairKey !== "password") {
      return this.validatePairedFields(field, pairKey, options);
    }

    var input = this.fieldInputNode(field);
    if (!input) return true;

    var payloadKey = this.payloadKey(field);
    if (!options.force && this.validState[field] === true && this.lastPayloadKey[field] === payloadKey) {
      return true;
    }

    if (this.pending[field]) {
      return this.validState[field] === true;
    }

    this.clearFieldState(field);
    this.setGlobalError("");

    var value = (input.value || "").trim();
    if (!value && this.requiredFields.indexOf(field) !== -1) {
      this.applyFieldError(field, "This field is required.");
      this.lastPayloadKey[field] = payloadKey;
      this.updateSubmitState();
      return false;
    }

    this.pending[field] = true;
    this.updateSubmitState();
    try {
      var response = await fetch(this.endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": this.csrf
        },
        body: JSON.stringify(this.serializePayload(field))
      });
      if (response.status === 429) {
        return this.handleThrottleResponse();
      }
      var json = await response.json().catch(function () {
        return {};
      });
      if (!response.ok || !json.valid) {
        var message = this.resolveFieldMessage(field, json.errors) || "Please check this field.";
        this.applyFieldError(field, message);
        this.lastPayloadKey[field] = payloadKey;
        return false;
      }
      this.applyFieldValid(field);
      this.lastPayloadKey[field] = payloadKey;
      return true;
    } catch (error) {
      this.setGlobalError("Could not validate right now. Please try again.");
      return false;
    } finally {
      this.pending[field] = false;
      this.updateSubmitState();
    }
  };

  PublicFormValidation.prototype.validatePasswordPair = async function (options) {
    options = options || {};
    var passwordInput = this.fieldInputNode("password");
    var confirmInput = this.fieldInputNode("password_confirmation");
    if (!passwordInput || !confirmInput) return true;

    var password = passwordInput.value || "";
    var confirm = confirmInput.value || "";
    var cacheField = "password_confirmation";
    var payloadKey = this.payloadKey(cacheField);

    if (!options.force && this.validState.password === true && this.validState.password_confirmation === true && this.lastPayloadKey[cacheField] === payloadKey) {
      return true;
    }

    if (this.pending.password || this.pending.password_confirmation) {
      return this.validState.password === true && this.validState.password_confirmation === true;
    }

    this.setGlobalError("");
    this.clearFieldState("password");
    this.clearFieldState("password_confirmation");

    if (!password) {
      this.applyFieldError("password", "This field is required.");
      this.lastPayloadKey[cacheField] = payloadKey;
      this.updateSubmitState();
      return false;
    }

    if (!confirm) {
      this.validState.password = false;
      this.validState.password_confirmation = false;
      this.lastPayloadKey[cacheField] = payloadKey;
      this.updateSubmitState();
      return false;
    }

    if (password !== confirm) {
      this.applyFieldError("password_confirmation", this.passwordMismatchMessage);
      this.lastPayloadKey[cacheField] = payloadKey;
      this.updateSubmitState();
      return false;
    }

    this.pending.password = true;
    this.pending.password_confirmation = true;
    this.updateSubmitState();

    try {
      var response = await fetch(this.endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": this.csrf
        },
        body: JSON.stringify(this.serializePayload(cacheField))
      });
      if (response.status === 429) {
        return this.handleThrottleResponse();
      }
      var json = await response.json().catch(function () {
        return {};
      });
      if (!response.ok || !json.valid) {
        this.applyPasswordPairServerErrors(json.errors || {});
        this.lastPayloadKey[cacheField] = payloadKey;
        return false;
      }

      this.applyFieldValid("password");
      this.applyFieldValid("password_confirmation");
      this.lastPayloadKey[cacheField] = payloadKey;
      return true;
    } catch (error) {
      this.setGlobalError("Could not validate right now. Please try again.");
      return false;
    } finally {
      this.pending.password = false;
      this.pending.password_confirmation = false;
      this.updateSubmitState();
    }
  };

  PublicFormValidation.prototype.validatePairedFields = async function (field, pairKey, options) {
    options = options || {};
    var fields = this.pairedFields[pairKey] || [field];
    var payloadKey = this.payloadKey(field);
    var allValid = fields.every(function (name) {
      return this.validState[name] === true;
    }, this);

    if (!options.force && allValid && this.lastPayloadKey[field] === payloadKey) {
      return true;
    }

    fields.forEach(function (name) {
      this.clearFieldState(name);
    }, this);
    this.setGlobalError("");

    var hasEmpty = fields.some(function (name) {
      var input = this.fieldInputNode(name);
      return !input || !(input.value || "").trim();
    }, this);

    if (hasEmpty) {
      fields.forEach(function (name) {
        var input = this.fieldInputNode(name);
        if (!input || !(input.value || "").trim()) {
          this.applyFieldError(name, "This field is required.");
        }
      }, this);
      this.lastPayloadKey[field] = payloadKey;
      this.updateSubmitState();
      return false;
    }

    if (pairKey === "mobile") {
      var mobileInput = this.fieldInputNode("mobile");
      var rawMobile = mobileInput ? mobileInput.value : "";
      if (rawMobile && !/^[0-9]+$/.test(rawMobile)) {
        this.applyFieldError("mobile", this.mobileDigitsMessage);
        this.lastPayloadKey[field] = payloadKey;
        this.updateSubmitState();
        return false;
      }
    }

    if (this.pending[field]) {
      return allValid;
    }

    this.pending[field] = true;
    this.updateSubmitState();

    try {
      var response = await fetch(this.endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": this.csrf
        },
        body: JSON.stringify(this.serializePayload(field))
      });
      if (response.status === 429) {
        this.setGlobalError(this.throttleMessage);
        return false;
      }
      var json = await response.json().catch(function () {
        return {};
      });
      if (!response.ok || !json.valid) {
        if (pairKey === "mobile") {
          this.applyMobilePairServerErrors(json.errors || {});
        } else {
          fields.forEach(function (name) {
            var message = this.resolveFieldMessage(name, json.errors);
            if (message) {
              this.applyFieldError(name, message);
            }
          }, this);
        }
        this.lastPayloadKey[field] = payloadKey;
        return false;
      }

      fields.forEach(function (name) {
        this.applyFieldValid(name);
      }, this);
      this.lastPayloadKey[field] = payloadKey;
      return true;
    } catch (error) {
      this.setGlobalError("Could not validate right now. Please try again.");
      return false;
    } finally {
      this.pending[field] = false;
      this.updateSubmitState();
    }
  };

  PublicFormValidation.prototype.updateSubmitState = function () {
    if (!this.submitButton) return;
    var pendingFields = Object.keys(this.pending).filter(function (key) {
      return !!this.pending[key];
    }, this);
    var allValid = this.requiredFields.every(function (field) {
      return this.validState[field] === true;
    }, this);
    this.submitButton.disabled = pendingFields.length > 0 || !allValid;
  };

  PublicFormValidation.prototype.install = function () {
    var self = this;
    var debounced = {};

    this.requiredFields.forEach(function (field) {
      self.validState[field] = false;
      debounced[field] = debounce(function () {
        self.validateField(field);
      }, 350);
      var input = self.fieldInputNode(field);
      if (!input) return;

      input.addEventListener("input", function () {
        self.clearPayloadCache(field);
        if (self.isPasswordPair(field)) {
          self.clearPairState("password");
          if (self.validatePasswordPairLocally()) {
            debounced.password_confirmation();
          } else {
            self.updateSubmitState();
          }
          return;
        }

        if (self.isMobilePair(field)) {
          if (field === "mobile" && input.value) {
            input.value = input.value.replace(/\D+/g, "");
          }
          self.clearPairState("mobile");
          self.validState.mobile_country_code = false;
          self.validState.mobile = false;
          self.updateSubmitState();
          debounced[field]();
          return;
        }

        self.clearFieldState(field);
        self.validState[field] = false;
        self.updateSubmitState();
        debounced[field]();
      });

      input.addEventListener("blur", function () {
        self.validateField(field);
      });

      if (field === "mobile_country_code") {
        input.addEventListener("change", function () {
          self.validateField("mobile_country_code");
        });
      }
    });

    this.form.addEventListener("submit", async function (event) {
      event.preventDefault();
      self.setGlobalError("");
      if (self.submitButton && self.submitButton.dataset.submitting === "1") return;

      var fieldsToValidate = [];
      var seenPairs = {};

      self.requiredFields.forEach(function (field) {
        var pairKey = self.getPairKey(field);
        if (pairKey) {
          if (seenPairs[pairKey]) return;
          seenPairs[pairKey] = true;
          fieldsToValidate.push(field);
          return;
        }
        fieldsToValidate.push(field);
      });

      var results = await Promise.all(fieldsToValidate.map(function (field) {
        var needsForce = self.validState[field] !== true;
        return self.validateField(field, { force: needsForce });
      }));
      var hasError = results.some(function (ok) {
        return !ok;
      });
      if (hasError) {
        if (self.submitButton) {
          self.submitButton.dataset.submitting = "";
          self.updateSubmitState();
        }
        return;
      }

      if (self.submitButton) {
        self.submitButton.dataset.submitting = "1";
        self.submitButton.disabled = true;
      }
      self.form.submit();
    });

    this.updateSubmitState();
  };

  function AgentRegistrationFormValidation(form, options) {
    this.form = form;
    this.endpoint = options.endpoint || "";
    this.csrf = options.csrf || "";
    this.fields = options.fields || [];
    this.mobileDigitsMessage = options.mobileDigitsMessage || "Only numbers are allowed. Do not use spaces, dashes, brackets, or special characters.";
    this.debounceMs = options.debounceMs || 350;
    this.mobileFields = ["mobile_country_code", "mobile"];
    this.pending = {};
    this.lastPayloadKey = {};
  }

  AgentRegistrationFormValidation.prototype.fieldErrorNode = function (field) {
    return this.form.querySelector('[data-error-for="' + field + '"]');
  };

  AgentRegistrationFormValidation.prototype.fieldInputNode = function (field) {
    if (field === "terms") {
      return this.form.querySelector('[name="terms"]');
    }
    return this.form.querySelector('[name="' + field + '"]');
  };

  AgentRegistrationFormValidation.prototype.clearFieldState = function (field) {
    var input = this.fieldInputNode(field);
    var error = this.fieldErrorNode(field);
    if (error) error.textContent = "";
    if (input && field !== "terms") {
      input.classList.remove("input-error");
      input.classList.remove("input-valid");
    }
  };

  AgentRegistrationFormValidation.prototype.resolveFieldMessage = function (field, errors) {
    var messages = (errors && errors[field]) || [];
    return messages.length ? messages[0] : "";
  };

  AgentRegistrationFormValidation.prototype.applyFieldError = function (field, message) {
    var text = message || "";
    if (field === "mobile" && text && text.indexOf("numbers") !== -1) {
      text = this.mobileDigitsMessage;
    }
    var input = this.fieldInputNode(field);
    var error = this.fieldErrorNode(field);
    if (error) error.textContent = text;
    if (input && field !== "terms") {
      input.classList.add("input-error");
      input.classList.remove("input-valid");
    }
  };

  AgentRegistrationFormValidation.prototype.applyFieldValid = function (field) {
    var input = this.fieldInputNode(field);
    if (input && field !== "terms") {
      input.classList.remove("input-error");
      input.classList.add("input-valid");
    }
  };

  AgentRegistrationFormValidation.prototype.applyMobileErrors = function (errors) {
    var codeMessage = this.resolveFieldMessage("mobile_country_code", errors);
    var mobileMessage = this.resolveFieldMessage("mobile", errors);
    if (mobileMessage && (mobileMessage.indexOf("numbers") !== -1 || mobileMessage.indexOf("regex") !== -1)) {
      mobileMessage = this.mobileDigitsMessage;
    }
    if (codeMessage) {
      this.applyFieldError("mobile_country_code", codeMessage);
    } else {
      this.clearFieldState("mobile_country_code");
    }
    if (mobileMessage) {
      this.applyFieldError("mobile", mobileMessage);
    } else {
      this.clearFieldState("mobile");
    }
    if (!codeMessage && !mobileMessage) {
      this.applyFieldValid("mobile_country_code");
      this.applyFieldValid("mobile");
    }
  };

  AgentRegistrationFormValidation.prototype.isMobileField = function (field) {
    return this.mobileFields.indexOf(field) !== -1;
  };

  AgentRegistrationFormValidation.prototype.fieldsForRequest = function (field) {
    if (this.isMobileField(field)) {
      return this.mobileFields.slice();
    }
    return [field];
  };

  AgentRegistrationFormValidation.prototype.readFieldValue = function (field) {
    if (field === "terms") {
      var termsInput = this.fieldInputNode("terms");
      return termsInput && termsInput.checked ? "1" : "";
    }
    var input = this.fieldInputNode(field);
    return input ? String(input.value || "").trim() : "";
  };

  AgentRegistrationFormValidation.prototype.buildPayload = function (field) {
    var payload = { field: field };
    for (var i = 0; i < this.fields.length; i++) {
      var name = this.fields[i];
      payload[name] = this.readFieldValue(name);
    }
    return payload;
  };

  AgentRegistrationFormValidation.prototype.validateField = async function (field) {
    if (!this.endpoint) return;

    var requestFields = this.fieldsForRequest(field);
    var payload = this.buildPayload(field);
    var payloadKey = JSON.stringify(payload);
    if (this.lastPayloadKey[field] === payloadKey || this.pending[field]) {
      return;
    }

    requestFields.forEach(function (name) {
      this.clearFieldState(name);
    }, this);

    if (this.isMobileField(field)) {
      var mobileInput = this.fieldInputNode("mobile");
      var rawMobile = mobileInput ? mobileInput.value : "";
      if (rawMobile && !/^[0-9]+$/.test(rawMobile)) {
        this.applyFieldError("mobile", this.mobileDigitsMessage);
        this.lastPayloadKey[field] = payloadKey;
        return;
      }
    }

    this.pending[field] = true;
    try {
      var response = await fetch(this.endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": this.csrf
        },
        body: JSON.stringify(payload)
      });
      var json = await response.json().catch(function () {
        return {};
      });

      if (response.status === 422 || json.success === false) {
        if (this.isMobileField(field)) {
          this.applyMobileErrors(json.errors || {});
        } else {
          requestFields.forEach(function (name) {
            var message = this.resolveFieldMessage(name, json.errors);
            if (message) {
              this.applyFieldError(name, message);
            }
          }, this);
        }
        this.lastPayloadKey[field] = payloadKey;
        return;
      }

      if (response.ok && json.success === true) {
        if (this.isMobileField(field)) {
          this.applyFieldValid("mobile_country_code");
          this.applyFieldValid("mobile");
        } else {
          requestFields.forEach(function (name) {
            this.applyFieldValid(name);
          }, this);
        }
        this.lastPayloadKey[field] = payloadKey;
      }
    } catch (error) {
      return;
    } finally {
      this.pending[field] = false;
    }
  };

  AgentRegistrationFormValidation.prototype.install = function () {
    var self = this;
    var debounced = {};

    this.fields.forEach(function (field) {
      if (field === "terms") {
        var termsInput = self.fieldInputNode("terms");
        if (!termsInput) return;
        termsInput.addEventListener("change", function () {
          self.lastPayloadKey.terms = "";
          self.validateField("terms");
        });
        return;
      }

      debounced[field] = debounce(function () {
        self.validateField(field);
      }, self.debounceMs);

      var input = self.fieldInputNode(field);
      if (!input) return;

      input.addEventListener("input", function () {
        self.lastPayloadKey[field] = "";
        if (self.isMobileField(field)) {
          if (field === "mobile" && input.value) {
            input.value = input.value.replace(/\D+/g, "");
          }
          self.clearFieldState("mobile_country_code");
          self.clearFieldState("mobile");
        } else {
          self.clearFieldState(field);
        }
        debounced[field]();
      });

      input.addEventListener("blur", function () {
        self.validateField(field);
      });

      if (field === "mobile_country_code") {
        input.addEventListener("change", function () {
          self.lastPayloadKey.mobile_country_code = "";
          self.lastPayloadKey.mobile = "";
          self.validateField("mobile_country_code");
        });
      }
    });
  };

  window.PublicFormValidation = PublicFormValidation;
  window.AgentRegistrationFormValidation = AgentRegistrationFormValidation;
})();
