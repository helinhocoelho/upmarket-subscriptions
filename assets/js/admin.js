(function ($) {
  "use strict";

  class UPMktAdmin {
    constructor() {
      this.init();
    }

    init() {
      this.initAccordions();
      this.bindEvents();
    }

    initAccordions() {
      // Fecha todos os accordions inicialmente
      $(".upmkt-accordion-content").hide();
      $(".upmkt-accordion-toggle").attr("aria-expanded", "false");
      $(".upmkt-accordion-icon").text("+");

      // Auto-abre accordion se houver erros de validação
      $(".upmkt-gateway-accordion").each((index, accordion) => {
        const $accordion = $(accordion);
        const $form = $accordion.find("form");

        // Verifica se há mensagens de erro do WordPress
        if ($form.find(".notice-error, .error").length > 0) {
          this.openAccordion($accordion);
        }
      });
    }

    bindEvents() {
      // Accordion functionality
      $(document).on(
        "click",
        ".upmkt-accordion-toggle",
        this.handleAccordionToggle.bind(this)
      );

      // Test connection functionality
      $(document).on(
        "click",
        ".upmkt-test-connection",
        this.handleTestConnection.bind(this)
      );

      // Cancelar assinatura
      $(document).on(
        "click",
        ".upmkt-cancel-subscription",
        this.handleCancelSubscription.bind(this)
      );

      // Exportar dados
      $(document).on(
        "click",
        ".upmkt-export-btn",
        this.handleExport.bind(this)
      );

      // Toggle de detalhes
      $(document).on(
        "click",
        ".upmkt-toggle-details",
        this.toggleDetails.bind(this)
      );
    }

    handleAccordionToggle(e) {
      e.preventDefault();

      const $toggle = $(e.currentTarget);
      const $accordion = $toggle.closest(".upmkt-gateway-accordion");
      const $content = $accordion.find(".upmkt-accordion-content");
      const isExpanded = $toggle.attr("aria-expanded") === "true";

      // Se já está expandido, apenas fecha
      if (isExpanded) {
        this.closeAccordion($accordion);
        return;
      }

      // Fecha todos os outros accordions
      this.closeAllAccordions();

      // Abre o accordion clicado
      this.openAccordion($accordion);
    }

    openAccordion($accordion) {
      const $toggle = $accordion.find(".upmkt-accordion-toggle");
      const $content = $accordion.find(".upmkt-accordion-content");
      const $icon = $toggle.find(".upmkt-accordion-icon");

      $toggle.attr("aria-expanded", "true");
      $content.slideDown(200);
      $icon.text("−");
      $accordion.addClass("upmkt-accordion-active");
    }

    closeAccordion($accordion) {
      const $toggle = $accordion.find(".upmkt-accordion-toggle");
      const $content = $accordion.find(".upmkt-accordion-content");
      const $icon = $toggle.find(".upmkt-accordion-icon");

      $toggle.attr("aria-expanded", "false");
      $content.slideUp(200);
      $icon.text("+");
      $accordion.removeClass("upmkt-accordion-active");
    }

    closeAllAccordions() {
      $(".upmkt-gateway-accordion").each((index, accordion) => {
        this.closeAccordion($(accordion));
      });
    }

    handleTestConnection(e) {
      e.preventDefault();

      const $button = $(e.currentTarget);
      const gatewayId = $button.data("gateway");
      const $result = $(`#upmkt-test-result-${gatewayId}`);

      $button.prop("disabled", true).text("Testando...");
      $result.hide().removeClass("success error");

      $.post(ajaxurl, {
        action: "upmkt_test_gateway_connection",
        gateway_id: gatewayId,
        nonce: upmkt_admin?.nonce || $("#upmkt_admin_nonce").val(),
      })
        .done((response) => {
          $result
            .show()
            .addClass(response.success ? "success" : "error")
            .html(
              `<p>${
                response.data?.message || "Resposta inválida do servidor"
              }</p>`
            );
        })
        .fail(() => {
          $result
            .show()
            .addClass("error")
            .html("<p>Erro ao testar conexão.</p>");
        })
        .always(() => {
          $button.prop("disabled", false).text("Testar Conexão");
        });
    }

    handleCancelSubscription(e) {
      e.preventDefault();

      const $button = $(e.currentTarget);
      const subscriptionId = $button.data("subscription-id");

      if (
        !confirm(
          upmkt_admin?.i18n?.confirm_cancel ||
            "Tem certeza que deseja cancelar esta assinatura?"
        )
      ) {
        return;
      }

      $button
        .prop("disabled", true)
        .text(upmkt_admin?.i18n?.processing || "Processando...");

      $.ajax({
        url: upmkt_admin?.ajax_url || ajaxurl,
        type: "POST",
        data: {
          action: "upmkt_admin_cancel_subscription",
          subscription_id: subscriptionId,
          nonce: upmkt_admin?.nonce,
        },
        success: (response) => {
          if (response.success) {
            this.showNotice(response.data.message, "success");
            $button.closest("tr").fadeOut();
          } else {
            this.showNotice(
              response.data?.message || "Erro ao cancelar assinatura",
              "error"
            );
            $button.prop("disabled", false).text("Cancelar");
          }
        },
        error: () => {
          this.showNotice("Erro de conexão.", "error");
          $button.prop("disabled", false).text("Cancelar");
        },
      });
    }

    handleExport(e) {
      e.preventDefault();

      const $button = $(e.currentTarget);
      const format = $button.data("format") || "csv";

      $button.prop("disabled", true).text("Exportando...");

      // Simula export - implementar AJAX real posteriormente
      setTimeout(() => {
        this.showNotice(
          "Exportação iniciada. Verifique seu e-mail.",
          "success"
        );
        $button.prop("disabled", false).text("Exportar");
      }, 1000);
    }

    toggleDetails(e) {
      e.preventDefault();

      const $trigger = $(e.currentTarget);
      const $details = $trigger.next(".upmkt-details");

      $details.slideToggle();
      $trigger.text(
        $details.is(":visible") ? "Ocultar Detalhes" : "Ver Detalhes"
      );
    }

    showNotice(message, type = "info") {
      const noticeClass =
        type === "error"
          ? "notice-error"
          : type === "success"
          ? "notice-success"
          : "notice-info";

      const $notice = $(
        `<div class="notice ${noticeClass} is-dismissible" style="margin-top: 20px;">
          <p>${message}</p>
          <button type="button" class="notice-dismiss"></button>
        </div>`
      );

      $(".wrap").prepend($notice);

      // Auto-remove após 5 segundos
      setTimeout(() => {
        $notice.fadeOut(() => $notice.remove());
      }, 5000);

      // Remove ao clicar no dismiss
      $notice.find(".notice-dismiss").on("click", function () {
        $notice.fadeOut(() => $notice.remove());
      });
    }
  }

  // Inicializar quando documento estiver pronto
  $(document).ready(() => {
    new UPMktAdmin();
  });
})(jQuery);
