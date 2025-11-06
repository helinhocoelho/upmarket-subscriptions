jQuery(document).ready(function ($) {
  // Tabs do formulário de registro
  if ($(".upmkt-tab").length > 0) {
    $(".upmkt-tab").on("click", function () {
      var tabId = $(this).data("tab");
      $(".upmkt-tab").removeClass("active");
      $(".upmkt-tab-content").removeClass("active");
      $(this).addClass("active");
      $("#" + tabId).addClass("active");
    });
  }

  // Função para aplicar máscara de CPF
  function applyCPFMask(value) {
    return value
      .replace(/\D/g, "")
      .replace(/(\d{3})(\d)/, "$1.$2")
      .replace(/(\d{3})(\d)/, "$1.$2")
      .replace(/(\d{3})(\d{1,2})$/, "$1-$2");
  }

  // Função para aplicar máscara de CNPJ
  function applyCNPJMask(value) {
    return value
      .replace(/\D/g, "")
      .replace(/(\d{2})(\d)/, "$1.$2")
      .replace(/(\d{3})(\d)/, "$1.$2")
      .replace(/(\d{3})(\d)/, "$1/$2")
      .replace(/(\d{4})(\d{1,2})$/, "$1-$2");
  }

  // Função para atualizar máscara e placeholder baseado no tipo selecionado
  function updateDocumentMask() {
    var documentType = $('input[name="document_type"]:checked').val();
    var $documentField = $("#upmkt_document");

    // VERIFICA SE O ELEMENTO EXISTE ANTES DE USAR
    if (!$documentField.length) return;

    // Remove qualquer máscara atual
    var currentValue = $documentField.val().replace(/\D/g, "");

    if (documentType === "cpf") {
      // Aplica máscara de CPF
      $documentField.val(applyCPFMask(currentValue));
      $documentField.attr("placeholder", "000.000.000-00");
      $documentField.attr("maxlength", 14);
    } else {
      // Aplica máscara de CNPJ
      $documentField.val(applyCNPJMask(currentValue));
      $documentField.attr("placeholder", "00.000.000/0000-00");
      $documentField.attr("maxlength", 18);
    }
  }

  // Máscara dinâmica para CPF/CNPJ
  if ($("#upmkt_document").length > 0) {
    $("#upmkt_document").on("input", function () {
      var documentType = $('input[name="document_type"]:checked').val();
      var value = $(this).val().replace(/\D/g, "");

      if (documentType === "cpf") {
        $(this).val(applyCPFMask(value));
      } else {
        $(this).val(applyCNPJMask(value));
      }
    });

    // Altera a máscara quando o tipo de documento mudar
    $('input[name="document_type"]').on("change", function () {
      updateDocumentMask();
    });

    // Inicializa a máscara quando a página carrega
    updateDocumentMask();
  }

  // =============================================
  // MÁSCARAS E VALIDAÇÕES DO CHECKOUT
  // =============================================

  // Máscara para número do cartão
  function applyCardNumberMask(value) {
    return value
      .replace(/\D/g, "")
      .replace(/(\d{4})(\d)/, "$1 $2")
      .replace(/(\d{4})(\d)/, "$1 $2")
      .replace(/(\d{4})(\d)/, "$1 $2")
      .replace(/(\d{4})(\d{1,4})/, "$1 $2")
      .trim()
      .substring(0, 19);
  }

  // Máscara para validade do cartão
  function applyCardExpiryMask(value) {
    return value
      .replace(/\D/g, "")
      .replace(/(\d{2})(\d)/, "$1/$2")
      .replace(/(\/\d{2})\d+?$/, "$1")
      .substring(0, 5);
  }

  // Máscara para CVV
  function applyCardCVVMask(value) {
    return value.replace(/\D/g, "").substring(0, 4);
  }

  // Máscara para nome no cartão - NOVA
  function applyCardHolderMask(value) {
    return value.replace(/[^a-zA-ZÀ-ÿ\s]/g, "");
  }

  // Validação do número do cartão usando algoritmo de Luhn
  function validateCardNumber(cardNumber) {
    const cleanNumber = cardNumber.replace(/\s+/g, "");

    // Verifica se tem entre 13 e 19 dígitos
    if (!/^\d{13,19}$/.test(cleanNumber)) {
      return false;
    }

    // Algoritmo de Luhn
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

  // Validação da data de validade
  function validateCardExpiry(expiry) {
    if (!/^\d{2}\/\d{2}$/.test(expiry)) {
      return false;
    }

    const [month, year] = expiry.split("/").map(Number);
    const currentDate = new Date();
    const currentYear = currentDate.getFullYear() % 100;
    const currentMonth = currentDate.getMonth() + 1;

    // Valida mês (1-12)
    if (month < 1 || month > 12) {
      return false;
    }

    // Valida ano não expirado
    if (year < currentYear) {
      return false;
    }

    // Se ano atual, valida mês não expirado
    if (year === currentYear && month < currentMonth) {
      return false;
    }

    return true;
  }

  // Validação do CVV
  function validateCardCVV(cvv) {
    return /^\d{3,4}$/.test(cvv);
  }

  // Validação do nome no cartão
  function validateCardHolder(name) {
    const cleanName = name.trim();
    return cleanName.length >= 2 && /^[a-zA-ZÀ-ÿ\s]+$/.test(cleanName);
  }

  // Inicialização das máscaras do checkout - ATUALIZADA
  function initCheckoutMasks() {
    const $cardNumber = $("#card_number");
    const $cardExpiry = $("#card_expiry");
    const $cardCVV = $("#card_cvv");
    const $cardHolder = $("#card_holder");

    // Máscara para número do cartão
    if ($cardNumber.length > 0) {
      $cardNumber.on("input", function () {
        const $this = $(this);
        const value = $this.val();
        const newValue = applyCardNumberMask(value);
        $this.val(newValue);
      });
    }

    // Máscara para validade
    if ($cardExpiry.length > 0) {
      $cardExpiry.on("input", function () {
        const $this = $(this);
        const value = $this.val();
        const newValue = applyCardExpiryMask(value);
        $this.val(newValue);
      });
    }

    // Máscara para CVV
    if ($cardCVV.length > 0) {
      $cardCVV.on("input", function () {
        const $this = $(this);
        const value = $this.val();
        const newValue = applyCardCVVMask(value);
        $this.val(newValue);
      });
    }

    // Máscara para nome no cartão - NOVA
    if ($cardHolder.length > 0) {
      $cardHolder.on("input", function () {
        const $this = $(this);
        const value = $this.val();
        const newValue = applyCardHolderMask(value);
        $this.val(newValue);
      });
    }
  }

  // Validação completa do formulário antes do envio
  function validateCheckoutForm() {
    let isValid = true;
    const errors = [];

    const $cardNumber = $("#card_number");
    const $cardExpiry = $("#card_expiry");
    const $cardCVV = $("#card_cvv");
    const $cardHolder = $("#card_holder");

    // Limpa erros anteriores
    $(".upmkt-input-error").removeClass("upmkt-input-error");
    $(".upmkt-field-error").remove();

    // Valida número do cartão
    if ($cardNumber.length > 0) {
      const cardNumber = $cardNumber.val().replace(/\s+/g, "");
      if (!cardNumber) {
        errors.push("Número do cartão é obrigatório");
        $cardNumber.addClass("upmkt-input-error");
        isValid = false;
      } else if (!validateCardNumber(cardNumber)) {
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
      } else if (!validateCardExpiry(cardExpiry)) {
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
      } else if (!validateCardCVV(cardCVV)) {
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
      } else if (!validateCardHolder(cardHolder)) {
        errors.push("Nome no cartão deve conter apenas letras e espaços");
        $cardHolder.addClass("upmkt-input-error");
        isValid = false;
      }
    }

    return { isValid, errors };
  }

  // Inicializa máscaras do checkout
  initCheckoutMasks();

  // =============================================
  // REGISTRO
  // =============================================

  // Processamento do registro
  if ($("#upmkt-registration-form").length > 0) {
    $("#upmkt-registration-form").on("submit", function (e) {
      e.preventDefault();

      var $form = $(this);
      var $submit = $form.find(".upmkt-submit-button");
      var $loading = $form.find(".upmkt-loading");
      var $messages = $form.find(".upmkt-messages");

      $submit.prop("disabled", true);
      $loading.show();
      $messages.empty();

      $.ajax({
        url: upmkt_front.ajax_url,
        type: "POST",
        data: $form.serialize(),
        success: function (response) {
          if (response.success) {
            $messages.html(
              '<div class="upmkt-message success">' +
                response.data.message +
                "</div>"
            );
            // Recarrega a página para usuário logado acessar o checkout
            setTimeout(function () {
              window.location.reload();
            }, 1500);
          } else {
            var errorHtml =
              '<div class="upmkt-message error"><strong>Erro:</strong><ul>';
            response.data.errors.forEach(function (error) {
              errorHtml += "<li>" + error + "</li>";
            });
            errorHtml += "</ul></div>";

            $messages.html(errorHtml);
          }
        },
        error: function () {
          $messages.html(
            '<div class="upmkt-message error">' +
              upmkt_front.i18n.error +
              "</div>"
          );
        },
        complete: function () {
          $submit.prop("disabled", false);
          $loading.hide();
        },
      });
    });
  }

  // Processamento do checkout COM VALIDAÇÃO
  if ($("#upmkt-checkout-form").length > 0) {
    $("#upmkt-checkout-form").on("submit", function (e) {
      e.preventDefault();

      // Valida o formulário antes do envio
      const validation = validateCheckoutForm();

      if (!validation.isValid) {
        const $messages = $(this).find(".upmkt-messages");
        let errorHtml =
          '<div class="upmkt-message error"><strong>Erro:</strong><ul>';
        validation.errors.forEach(function (error) {
          errorHtml += "<li>" + error + "</li>";
        });
        errorHtml += "</ul></div>";

        $messages.html(errorHtml);
        return;
      }

      var $form = $(this);
      var $submit = $form.find(".upmkt-submit-button");
      var $loading = $form.find(".upmkt-loading");
      var $messages = $form.find(".upmkt-messages");

      $submit.prop("disabled", true);
      $loading.show();
      $messages.empty();

      $.ajax({
        url: upmkt_front.ajax_url,
        type: "POST",
        data: $form.serialize(),
        success: function (response) {
          if (response.success) {
            $messages.html(
              '<div class="upmkt-message success">' +
                response.data.message +
                "</div>"
            );

            // Redireciona após sucesso
            if (response.data.redirect_url) {
              setTimeout(function () {
                window.location.href = response.data.redirect_url;
              }, 2000);
            }
          } else {
            var errorHtml =
              '<div class="upmkt-message error"><strong>Erro:</strong><ul>';
            response.data.errors.forEach(function (error) {
              errorHtml += "<li>" + error + "</li>";
            });
            errorHtml += "</ul></div>";

            $messages.html(errorHtml);
          }
        },
        error: function () {
          $messages.html(
            '<div class="upmkt-message error">' +
              upmkt_front.i18n.error +
              "</div>"
          );
        },
        complete: function () {
          $submit.prop("disabled", false);
          $loading.hide();
        },
      });
    });
  }

  // =============================================
  // ÁREA DO USUÁRIO
  // =============================================

  // Sistema de Notificações
  function upmktShowNotification(message, type = "success") {
    // Criar notificação se não existir
    let notification = document.getElementById("upmkt-notification");
    if (!notification) {
      notification = document.createElement("div");
      notification.id = "upmkt-notification";
      notification.className = "upmkt-notification";
      notification.innerHTML = `
        <div class="upmkt-notification-content">
          <span id="upmkt-notification-message"></span>
          <button type="button" class="upmkt-notification-close">&times;</button>
        </div>
      `;
      document.body.appendChild(notification);

      // Adicionar event listener para fechar
      notification
        .querySelector(".upmkt-notification-close")
        .addEventListener("click", upmktHideNotification);
    }

    const messageEl = document.getElementById("upmkt-notification-message");
    notification.className = `upmkt-notification ${type}`;
    messageEl.textContent = message;
    notification.style.display = "block";

    // Auto-close após 5 segundos
    setTimeout(() => {
      upmktHideNotification();
    }, 5000);
  }

  function upmktHideNotification() {
    const notification = document.getElementById("upmkt-notification");
    if (notification) {
      notification.style.display = "none";
    }
  }

  // Modal de Confirmação
  function upmktShowConfirmModal(title, message, onConfirm) {
    // Criar modal se não existir
    let modal = document.getElementById("upmkt-confirm-modal");
    if (!modal) {
      modal = document.createElement("div");
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

      // Adicionar event listeners
      const closeBtn = modal.querySelector(".upmkt-modal-close");
      const cancelBtn = document.getElementById("upmkt-confirm-cancel");

      const closeModal = () => {
        modal.style.display = "none";
        modal.classList.remove("show");
      };

      closeBtn.addEventListener("click", closeModal);
      cancelBtn.addEventListener("click", closeModal);

      modal.addEventListener("click", function (e) {
        if (e.target === this) {
          closeModal();
        }
      });
    }

    document.getElementById("upmkt-confirm-title").textContent = title;
    document.getElementById("upmkt-confirm-message").textContent = message;

    // MOSTRAR MODAL CORRETAMENTE
    modal.style.display = "flex";
    setTimeout(() => modal.classList.add("show"), 10);

    // Configurar evento de confirmação
    const confirmOk = document.getElementById("upmkt-confirm-ok");

    // Remover event listeners anteriores para evitar duplicação
    const newConfirmOk = confirmOk.cloneNode(true);
    confirmOk.parentNode.replaceChild(newConfirmOk, confirmOk);

    newConfirmOk.onclick = () => {
      modal.style.display = "none";
      modal.classList.remove("show");
      onConfirm();
    };
  }

  // Ações das Assinaturas
  window.upmktCancelSubscription = function (subscriptionId) {
    upmktShowConfirmModal(
      "Cancelar Assinatura",
      "Tem certeza que deseja cancelar esta assinatura?\n\nApós o cancelamento, você perderá o acesso ao plano na data de vencimento. Você poderá criar uma nova assinatura a qualquer momento.",
      () =>
        upmktHandleSubscriptionAction(
          "upmkt_cancel_subscription",
          subscriptionId
        )
    );
  };

  window.upmktPauseSubscription = function (subscriptionId) {
    upmktShowConfirmModal(
      "Pausar recorrência",
      "Deseja pausar a recorrência?\n\nVocê manterá o acesso até a data de vencimento, mas não serão feitas novas cobranças. Após a data de vencimento, a assinatura será cancelada automaticamente.",
      () =>
        upmktHandleSubscriptionAction(
          "upmkt_pause_subscription",
          subscriptionId
        )
    );
  };

  window.upmktResumeSubscription = function (subscriptionId) {
    upmktShowConfirmModal(
      "Retomar Recorrência",
      "Deseja retomar a recorrência?\n\nAs cobranças serão reiniciadas a partir da próxima data de vencimento. Sua assinatura voltará ao estado ativo.",
      () =>
        upmktHandleSubscriptionAction(
          "upmkt_resume_subscription",
          subscriptionId
        )
    );
  };

  function upmktHandleSubscriptionAction(action, subscriptionId) {
    const button = document.querySelector(
      `[data-subscription-id="${subscriptionId}"][data-action="${action}"]`
    );
    if (!button) return;

    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML =
      '<span class="upmkt-loading">' + upmkt_front.i18n.processing + "</span>";

    $.ajax({
      url: upmkt_front.ajax_url,
      type: "POST",
      data: {
        action: action,
        subscription_id: subscriptionId,
        nonce: upmkt_front.nonce,
      },
      success: function (response) {
        if (response.success) {
          upmktShowNotification(
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
          upmktShowNotification(
            response.data.message || upmkt_front.i18n.error,
            "error"
          );
          button.disabled = false;
          button.innerHTML = originalText;
        }
      },
      error: function () {
        upmktShowNotification(upmkt_front.i18n.error, "error");
        button.disabled = false;
        button.innerHTML = originalText;
      },
    });
  }

  // Fazer pagamento pendente
  window.upmktRetryPayment = function (subscriptionId) {
    upmktShowConfirmModal(
      "Efeturar pagamento",
      "Você será redirecionado para a página de checkout para inserir os dados do cartão novamente.\n\nDeseja continuar?",
      () => upmktHandleSubscriptionAction("upmkt_retry_payment", subscriptionId)
    );
  };

  // Limpa erros quando o usuário começa a digitar nos campos do checkout
  $(document).on(
    "input",
    "#card_number, #card_expiry, #card_cvv, #card_holder",
    function () {
      const $this = $(this);
      $this.removeClass("upmkt-input-error");
      $this.next(".upmkt-field-error").remove();
    }
  );
});
