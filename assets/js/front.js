jQuery(document).ready(function ($) {
  "use strict";

  class UPMktFrontend {
    constructor() {
      this.init();
    }

    init() {
      this.initTabs();
      this.initDocumentMasks();
      this.initCheckoutMasks();
      this.initForms();
      this.initSubscriptionActions();
    }

    // =============================================
    // SISTEMA DE TABS
    // =============================================

    initTabs() {
      if ($(".upmkt-tab").length > 0) {
        $(".upmkt-tab").on("click", (e) => {
          const $tab = $(e.currentTarget);
          const tabId = $tab.data("tab");

          $(".upmkt-tab").removeClass("active");
          $(".upmkt-tab-content").removeClass("active");

          $tab.addClass("active");
          $("#" + tabId).addClass("active");
        });
      }
    }

    // =============================================
    // MÁSCARAS DE DOCUMENTOS (CPF/CNPJ)
    // =============================================

    initDocumentMasks() {
      if ($("#upmkt_document").length === 0) return;

      // Aplica máscara quando o documento é digitado
      $("#upmkt_document").on("input", (e) => {
        const $input = $(e.currentTarget);
        const documentType = $('input[name="document_type"]:checked').val();
        const value = $input.val().replace(/\D/g, "");

        $input.val(
          documentType === "cpf"
            ? this.applyCPFMask(value)
            : this.applyCNPJMask(value)
        );
      });

      // Altera a máscara quando o tipo muda
      $('input[name="document_type"]').on("change", () => {
        this.updateDocumentMask();
      });

      // Inicializa a máscara
      this.updateDocumentMask();
    }

    applyCPFMask(value) {
      return value
        .replace(/\D/g, "")
        .replace(/(\d{3})(\d)/, "$1.$2")
        .replace(/(\d{3})(\d)/, "$1.$2")
        .replace(/(\d{3})(\d{1,2})$/, "$1-$2");
    }

    applyCNPJMask(value) {
      return value
        .replace(/\D/g, "")
        .replace(/(\d{2})(\d)/, "$1.$2")
        .replace(/(\d{3})(\d)/, "$1.$2")
        .replace(/(\d{3})(\d)/, "$1/$2")
        .replace(/(\d{4})(\d{1,2})$/, "$1-$2");
    }

    updateDocumentMask() {
      const documentType = $('input[name="document_type"]:checked').val();
      const $documentField = $("#upmkt_document");

      if (!$documentField.length) return;

      const currentValue = $documentField.val().replace(/\D/g, "");

      if (documentType === "cpf") {
        $documentField.val(this.applyCPFMask(currentValue));
        $documentField.attr("placeholder", "000.000.000-00");
        $documentField.attr("maxlength", 14);
      } else {
        $documentField.val(this.applyCNPJMask(currentValue));
        $documentField.attr("placeholder", "00.000.000/0000-00");
        $documentField.attr("maxlength", 18);
      }
    }

    // =============================================
    // MÁSCARAS E VALIDAÇÕES DO CHECKOUT
    // =============================================

    initCheckoutMasks() {
      this.setupCardNumberMask();
      this.setupCardExpiryMask();
      this.setupCardCVVMask();
      this.setupCardHolderMask();
      this.setupInputErrorClearing();
    }

    setupCardNumberMask() {
      const $cardNumber = $("#card_number");
      if ($cardNumber.length > 0) {
        $cardNumber.on("input", (e) => {
          const $this = $(e.currentTarget);
          $this.val(this.applyCardNumberMask($this.val()));
        });
      }
    }

    setupCardExpiryMask() {
      const $cardExpiry = $("#card_expiry");
      if ($cardExpiry.length > 0) {
        $cardExpiry.on("input", (e) => {
          const $this = $(e.currentTarget);
          $this.val(this.applyCardExpiryMask($this.val()));
        });
      }
    }

    setupCardCVVMask() {
      const $cardCVV = $("#card_cvv");
      if ($cardCVV.length > 0) {
        $cardCVV.on("input", (e) => {
          const $this = $(e.currentTarget);
          $this.val(this.applyCardCVVMask($this.val()));
        });
      }
    }

    setupCardHolderMask() {
      const $cardHolder = $("#card_holder");
      if ($cardHolder.length > 0) {
        $cardHolder.on("input", (e) => {
          const $this = $(e.currentTarget);
          $this.val(this.applyCardHolderMask($this.val()));
        });
      }
    }

    setupInputErrorClearing() {
      $(document).on(
        "input",
        "#card_number, #card_expiry, #card_cvv, #card_holder",
        (e) => {
          const $this = $(e.currentTarget);
          $this.removeClass("upmkt-input-error");
          $this.next(".upmkt-field-error").remove();
        }
      );
    }

    applyCardNumberMask(value) {
      return value
        .replace(/\D/g, "")
        .replace(/(\d{4})(\d)/, "$1 $2")
        .replace(/(\d{4})(\d)/, "$1 $2")
        .replace(/(\d{4})(\d)/, "$1 $2")
        .replace(/(\d{4})(\d{1,4})/, "$1 $2")
        .trim()
        .substring(0, 19);
    }

    applyCardExpiryMask(value) {
      return value
        .replace(/\D/g, "")
        .replace(/(\d{2})(\d)/, "$1/$2")
        .replace(/(\/\d{2})\d+?$/, "$1")
        .substring(0, 5);
    }

    applyCardCVVMask(value) {
      return value.replace(/\D/g, "").substring(0, 4);
    }

    applyCardHolderMask(value) {
      return value.replace(/[^a-zA-ZÀ-ÿ\s]/g, "");
    }

    validateCardNumber(cardNumber) {
      const cleanNumber = cardNumber.replace(/\s+/g, "");

      if (!/^\d{13,19}$/.test(cleanNumber)) {
        return false;
      }

      let sum = 0;
      let isEven = false;

      for (let i = cleanNumber.length - 1; i >= 0; i--) {
        let digit = parseInt(cleanNumber.charAt(i), 10);

        if (isEven) {
          digit *= 2;
          if (digit > 9) {
            digit -= 9;
          }
        }

        sum += digit;
        isEven = !isEven;
      }

      return sum % 10 === 0;
    }

    validateCardExpiry(expiry) {
      if (!/^\d{2}\/\d{2}$/.test(expiry)) {
        return false;
      }

      const [month, year] = expiry.split("/").map(Number);
      const currentDate = new Date();
      const currentYear = currentDate.getFullYear() % 100;
      const currentMonth = currentDate.getMonth() + 1;

      if (month < 1 || month > 12) {
        return false;
      }

      if (year < currentYear) {
        return false;
      }

      if (year === currentYear && month < currentMonth) {
        return false;
      }

      return true;
    }

    validateCardCVV(cvv) {
      return /^\d{3,4}$/.test(cvv);
    }

    validateCardHolder(name) {
      const cleanName = name.trim();
      return cleanName.length >= 2 && /^[a-zA-ZÀ-ÿ\s]+$/.test(cleanName);
    }

    validateCheckoutForm() {
      let isValid = true;
      const errors = [];

      const $cardNumber = $("#card_number");
      const $cardExpiry = $("#card_expiry");
      const $cardCVV = $("#card_cvv");
      const $cardHolder = $("#card_holder");

      $(".upmkt-input-error").removeClass("upmkt-input-error");
      $(".upmkt-field-error").remove();

      // Valida número do cartão
      if ($cardNumber.length > 0) {
        const cardNumber = $cardNumber.val().replace(/\s+/g, "");
        if (!cardNumber) {
          errors.push("Número do cartão é obrigatório");
          $cardNumber.addClass("upmkt-input-error");
          isValid = false;
        } else if (!this.validateCardNumber(cardNumber)) {
          errors.push("Número do cartão inválido");
          $cardNumber.addClass("upmkt-input-error");
          isValid = false;
        }
      }

      // Valida validade
      if ($cardExpiry.length > 0) {
        const cardExpiry = $cardExpiry.val();
        if (!cardExpiry) {
          errors.push("Data de validade é obrigatória");
          $cardExpiry.addClass("upmkt-input-error");
          isValid = false;
        } else if (!this.validateCardExpiry(cardExpiry)) {
          errors.push("Data de validade inválida ou expirada");
          $cardExpiry.addClass("upmkt-input-error");
          isValid = false;
        }
      }

      // Valida CVV
      if ($cardCVV.length > 0) {
        const cardCVV = $cardCVV.val();
        if (!cardCVV) {
          errors.push("CVV é obrigatório");
          $cardCVV.addClass("upmkt-input-error");
          isValid = false;
        } else if (!this.validateCardCVV(cardCVV)) {
          errors.push("CVV inválido");
          $cardCVV.addClass("upmkt-input-error");
          isValid = false;
        }
      }

      // Valida nome no cartão
      if ($cardHolder.length > 0) {
        const cardHolder = $cardHolder.val().trim();
        if (!cardHolder) {
          errors.push("Nome no cartão é obrigatório");
          $cardHolder.addClass("upmkt-input-error");
          isValid = false;
        } else if (!this.validateCardHolder(cardHolder)) {
          errors.push("Nome no cartão deve conter apenas letras e espaços");
          $cardHolder.addClass("upmkt-input-error");
          isValid = false;
        }
      }

      return { isValid, errors };
    }

    // =============================================
    // PROCESSAMENTO DE FORMULÁRIOS
    // =============================================

    initForms() {
      this.initRegistrationForm();
      this.initCheckoutForm();
    }

    initRegistrationForm() {
      const $form = $("#upmkt-registration-form");
      if ($form.length === 0) return;

      $form.on("submit", (e) => {
        e.preventDefault();
        this.handleFormSubmission($form, "registration");
      });
    }

    initCheckoutForm() {
      const $form = $("#upmkt-checkout-form");
      if ($form.length === 0) return;

      $form.on("submit", (e) => {
        e.preventDefault();

        const validation = this.validateCheckoutForm();
        if (!validation.isValid) {
          this.showFormErrors($form, validation.errors);
          return;
        }

        this.handleFormSubmission($form, "checkout");
      });
    }

    handleFormSubmission($form, formType) {
      const $submit = $form.find(".upmkt-submit-button");
      const $loading = $form.find(".upmkt-loading");
      const $messages = $form.find(".upmkt-messages");

      $submit.prop("disabled", true);
      $loading.show();
      $messages.empty();

      $.ajax({
        url: upmkt_front.ajax_url,
        type: "POST",
        data: $form.serialize(),
        success: (response) => {
          if (response.success) {
            this.showFormSuccess($messages, response.data.message);
            this.handleFormSuccess(formType, response.data);
          } else {
            this.showFormErrors($messages, response.data.errors);
          }
        },
        error: () => {
          this.showFormError($messages, upmkt_front.i18n.error);
        },
        complete: () => {
          $submit.prop("disabled", false);
          $loading.hide();
        },
      });
    }

    showFormSuccess($messages, message) {
      $messages.html(
        '<div class="upmkt-message success">' + message + "</div>"
      );
    }

    showFormError($messages, error) {
      $messages.html('<div class="upmkt-message error">' + error + "</div>");
    }

    showFormErrors($messages, errors) {
      let errorHtml =
        '<div class="upmkt-message error"><strong>Erro:</strong><ul>';
      errors.forEach((error) => {
        errorHtml += "<li>" + error + "</li>";
      });
      errorHtml += "</ul></div>";
      $messages.html(errorHtml);
    }

    handleFormSuccess(formType, data) {
      if (formType === "registration") {
        setTimeout(() => {
          window.location.reload();
        }, 1500);
      } else if (formType === "checkout" && data.redirect_url) {
        setTimeout(() => {
          window.location.href = data.redirect_url;
        }, 2000);
      }
    }

    // =============================================
    // SISTEMA DE NOTIFICAÇÕES
    // =============================================

    showNotification(message, type = "success") {
      let notification = document.getElementById("upmkt-notification");

      if (!notification) {
        notification = this.createNotificationElement();
      }

      const messageEl = document.getElementById("upmkt-notification-message");
      notification.className = `upmkt-notification ${type}`;
      messageEl.textContent = message;
      notification.style.display = "block";

      setTimeout(() => {
        this.hideNotification();
      }, 5000);
    }

    createNotificationElement() {
      const notification = document.createElement("div");
      notification.id = "upmkt-notification";
      notification.className = "upmkt-notification";
      notification.innerHTML = `
        <div class="upmkt-notification-content">
          <span id="upmkt-notification-message"></span>
          <button type="button" class="upmkt-notification-close">&times;</button>
        </div>
      `;

      document.body.appendChild(notification);

      notification
        .querySelector(".upmkt-notification-close")
        .addEventListener("click", () => this.hideNotification());

      return notification;
    }

    hideNotification() {
      const notification = document.getElementById("upmkt-notification");
      if (notification) {
        notification.style.display = "none";
      }
    }

    // =============================================
    // MODAL DE CONFIRMAÇÃO
    // =============================================

    showConfirmModal(title, message, onConfirm) {
      let modal = document.getElementById("upmkt-confirm-modal");

      if (!modal) {
        modal = this.createModalElement();
      }

      document.getElementById("upmkt-confirm-title").textContent = title;
      document.getElementById("upmkt-confirm-message").textContent = message;

      modal.style.display = "flex";
      setTimeout(() => modal.classList.add("show"), 10);

      this.setupConfirmButton(modal, onConfirm);
    }

    createModalElement() {
      const modal = document.createElement("div");
      modal.id = "upmkt-confirm-modal";
      modal.className = "upmkt-modal";
      modal.style.display = "none";
      modal.innerHTML = `
        <div class="upmkt-modal-content">
          <div class="upmkt-modal-header">
            <h3 id="upmkt-confirm-title">Confirmação</h3>
            <button type="button" class="upmkt-modal-close">&times;</button>
          </div>
          <div class="upmkt-modal-body">
            <p id="upmkt-confirm-message"></p>
          </div>
          <div class="upmkt-modal-footer">
            <button type="button" class="upmkt-btn upmkt-btn-secondary" id="upmkt-confirm-cancel">Cancelar</button>
            <button type="button" class="upmkt-btn upmkt-btn-primary" id="upmkt-confirm-ok">Confirmar</button>
          </div>
        </div>
      `;

      document.body.appendChild(modal);
      this.setupModalEvents(modal);

      return modal;
    }

    setupModalEvents(modal) {
      const closeBtn = modal.querySelector(".upmkt-modal-close");
      const cancelBtn = document.getElementById("upmkt-confirm-cancel");

      const closeModal = () => {
        modal.style.display = "none";
        modal.classList.remove("show");
      };

      closeBtn.addEventListener("click", closeModal);
      cancelBtn.addEventListener("click", closeModal);

      modal.addEventListener("click", (e) => {
        if (e.target === modal) {
          closeModal();
        }
      });
    }

    setupConfirmButton(modal, onConfirm) {
      const confirmOk = document.getElementById("upmkt-confirm-ok");
      const newConfirmOk = confirmOk.cloneNode(true);
      confirmOk.parentNode.replaceChild(newConfirmOk, confirmOk);

      newConfirmOk.onclick = () => {
        modal.style.display = "none";
        modal.classList.remove("show");
        onConfirm();
      };
    }

    // =============================================
    // AÇÕES DE ASSINATURA
    // =============================================

    initSubscriptionActions() {
      // As funções são expostas para o escopo global para serem chamadas via HTML
      window.upmktCancelSubscription = (subscriptionId) =>
        this.handleSubscriptionAction(
          subscriptionId,
          "upmkt_cancel_subscription",
          "Cancelar Doação",
          "Tem certeza que deseja cancelar esta doação?\n\nApós o cancelamento, você perderá o acesso ao plano na data de vencimento. Você poderá criar uma nova doação a qualquer momento."
        );

      window.upmktPauseSubscription = (subscriptionId) =>
        this.handleSubscriptionAction(
          subscriptionId,
          "upmkt_pause_subscription",
          "Pausar recorrência",
          "Deseja pausar a recorrência?\n\nVocê manterá o acesso até a data de vencimento, mas não serão feitas novas cobranças. Após a data de vencimento, a doação será cancelada automaticamente."
        );

      window.upmktResumeSubscription = (subscriptionId) =>
        this.handleSubscriptionAction(
          subscriptionId,
          "upmkt_resume_subscription",
          "Retomar Recorrência",
          "Deseja retomar a recorrência?\n\nAs cobranças serão reiniciadas a partir da próxima data de vencimento. Sua doação voltará ao estado ativo."
        );

      window.upmktRetryPayment = (subscriptionId) =>
        this.handleSubscriptionAction(
          subscriptionId,
          "upmkt_retry_payment",
          "Efeturar pagamento",
          "Você será redirecionado para a página de checkout para inserir os dados do cartão novamente.\n\nDeseja continuar?"
        );
    }

    handleSubscriptionAction(subscriptionId, action, title, message) {
      this.showConfirmModal(title, message, () => {
        this.processSubscriptionAction(subscriptionId, action);
      });
    }

    processSubscriptionAction(subscriptionId, action) {
      const button = document.querySelector(
        `[data-subscription-id="${subscriptionId}"][data-action="${action}"]`
      );

      if (!button) return;

      const originalText = button.innerHTML;
      button.disabled = true;
      button.innerHTML = `<span class="upmkt-loading">${upmkt_front.i18n.processing}</span>`;

      $.ajax({
        url: upmkt_front.ajax_url,
        type: "POST",
        data: {
          action: action,
          subscription_id: subscriptionId,
          nonce: upmkt_front.nonce,
        },
        success: (response) => {
          if (response.success) {
            this.showNotification(
              response.data.message || "Ação realizada com sucesso!",
              "success"
            );

            if (response.data.redirect_url) {
              setTimeout(() => {
                window.location.href = response.data.redirect_url;
              }, 1500);
            } else {
              setTimeout(() => {
                location.reload();
              }, 1500);
            }
          } else {
            this.showNotification(
              response.data.message || upmkt_front.i18n.error,
              "error"
            );
            button.disabled = false;
            button.innerHTML = originalText;
          }
        },
        error: () => {
          this.showNotification(upmkt_front.i18n.error, "error");
          button.disabled = false;
          button.innerHTML = originalText;
        },
      });
    }
  }

  // Inicializar a aplicação
  new UPMktFrontend();
});
