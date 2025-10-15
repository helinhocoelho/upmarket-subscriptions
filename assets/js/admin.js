(function ($) {
  "use strict";

  class UPMktAdmin {
    constructor() {
      this.init();
    }

    init() {
      this.bindEvents();
    }

    bindEvents() {
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

    handleCancelSubscription(e) {
      e.preventDefault();

      const $button = $(e.target);
      const subscriptionId = $button.data("subscription-id");

      if (!confirm(upmkt_admin.i18n.confirm_cancel)) {
        return;
      }

      $button.prop("disabled", true).text(upmkt_admin.i18n.processing);

      $.ajax({
        url: upmkt_admin.ajax_url,
        type: "POST",
        data: {
          action: "upmkt_admin_cancel_subscription",
          subscription_id: subscriptionId,
          nonce: upmkt_admin.nonce,
        },
        success: (response) => {
          if (response.success) {
            this.showNotice(response.data.message, "success");
            $button.closest("tr").fadeOut();
          } else {
            this.showNotice(response.data.message, "error");
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

      const $button = $(e.target);
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

      const $trigger = $(e.target);
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
