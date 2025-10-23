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
});
