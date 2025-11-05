jQuery(document).ready(function ($) {
  // Tabs do formulário de registro - SÓ SE EXISTIR
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

  // Máscara dinâmica para CPF/CNPJ - SÓ SE EXISTIR
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

  // Processamento do registro - SÓ SE EXISTIR
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

  // Processamento do login - SÓ SE EXISTIR
  if ($("#upmkt-login-form").length > 0) {
    $("#upmkt-login-form").on("submit", function (e) {
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

  // Processamento do checkout - SÓ SE EXISTIR
  if ($("#upmkt-checkout-form").length > 0) {
    $("#upmkt-checkout-form").on("submit", function (e) {
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
  // CUSTOMER AREA FUNCTIONALITY
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

      closeBtn.addEventListener("click", () => (modal.style.display = "none"));
      cancelBtn.addEventListener("click", () => (modal.style.display = "none"));

      modal.addEventListener("click", function (e) {
        if (e.target === this) {
          this.style.display = "none";
        }
      });
    }

    document.getElementById("upmkt-confirm-title").textContent = title;
    document.getElementById("upmkt-confirm-message").textContent = message;

    modal.style.display = "flex";

    // Configurar evento de confirmação
    const confirmOk = document.getElementById("upmkt-confirm-ok");
    const oldOnClick = confirmOk.onclick;
    confirmOk.onclick = () => {
      modal.style.display = "none";
      confirmOk.onclick = oldOnClick; // Restaurar evento anterior
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
      "Pausar Recorrência",
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
      '<span class="upmkt-loading">⏳ ' +
      upmkt_front.i18n.processing +
      "</span>";

    // Usar jQuery para consistência com o resto do código
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
          // Recarregar a página após 2 segundos para mostrar mudanças
          setTimeout(() => {
            location.reload();
          }, 2000);
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
});
