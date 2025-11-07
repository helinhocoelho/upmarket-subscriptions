(function ($) {
  "use strict";

  class UPMktAdmin {
    constructor() {
      this.init();
    }

    init() {
      this.initAccordions();
      this.initTabs();
      this.bindEvents();
    }

    // =============================================
    // INICIALIZAÇÃO DE COMPONENTES
    // =============================================

    initAccordions() {
      // Fecha todos os accordions inicialmente
      $(".upmkt-accordion-content").hide();
      $(".upmkt-accordion-toggle").attr("aria-expanded", "false");
      $(".upmkt-accordion-icon").text("+");

      // Auto-abre accordion se houver erros de validação
      this.autoOpenAccordionsWithErrors();
    }

    initTabs() {
      $(".nav-tab").on("click", (e) => {
        e.preventDefault();
        this.handleTabClick(e);
      });
    }

    bindEvents() {
      this.bindAccordionEvents();
      this.bindTestConnectionEvents();
      this.bindSubscriptionEvents();
      this.bindExportEvents();
      this.bindDetailsEvents();
    }

    // =============================================
    // MANIPULAÇÃO DE ACCORDIONS
    // =============================================

    autoOpenAccordionsWithErrors() {
      $(".upmkt-gateway-accordion").each((index, accordion) => {
        const $accordion = $(accordion);
        const $form = $accordion.find("form");

        if ($form.find(".notice-error, .error").length > 0) {
          this.openAccordion($accordion);
        }
      });
    }

    handleAccordionToggle(e) {
      e.preventDefault();

      const $toggle = $(e.currentTarget);
      const $accordion = $toggle.closest(".upmkt-gateway-accordion");
      const isExpanded = $toggle.attr("aria-expanded") === "true";

      if (isExpanded) {
        this.closeAccordion($accordion);
      } else {
        this.closeAllAccordions();
        this.openAccordion($accordion);
      }
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

    bindAccordionEvents() {
      $(document).on(
        "click",
        ".upmkt-accordion-toggle",
        this.handleAccordionToggle.bind(this)
      );
    }

    // =============================================
    // SISTEMA DE TABS
    // =============================================

    handleTabClick(e) {
      const $tab = $(e.currentTarget);
      const target = $tab.attr("href");

      $(".nav-tab").removeClass("nav-tab-active");
      $(".tab-content").removeClass("active");

      $tab.addClass("nav-tab-active");
      $(target).addClass("active");
    }

    // =============================================
    // TESTE DE CONEXÃO COM GATEWAYS
    // =============================================

    handleTestConnection(e) {
      e.preventDefault();

      const $button = $(e.currentTarget);
      const gatewayId = $button.data("gateway");
      const $result = $(`#upmkt-test-result-${gatewayId}`);

      this.disableTestButton($button);
      this.resetTestResult($result);

      $.post(ajaxurl, {
        action: "upmkt_test_gateway_connection",
        gateway_id: gatewayId,
        nonce: this.getAdminNonce(),
      })
        .done((response) => this.handleTestSuccess(response, $result))
        .fail(() => this.handleTestFailure($result))
        .always(() => this.enableTestButton($button));
    }

    disableTestButton($button) {
      $button.prop("disabled", true).text("Testando...");
    }

    enableTestButton($button) {
      $button.prop("disabled", false).text("Testar Conexão");
    }

    resetTestResult($result) {
      $result.hide().removeClass("success error");
    }

    handleTestSuccess(response, $result) {
      const isSuccess = response.success;
      const message = response.data?.message || "Resposta inválida do servidor";

      $result
        .show()
        .addClass(isSuccess ? "success" : "error")
        .html(`<p>${message}</p>`);
    }

    handleTestFailure($result) {
      $result.show().addClass("error").html("<p>Erro ao testar conexão.</p>");
    }

    bindTestConnectionEvents() {
      $(document).on(
        "click",
        ".upmkt-test-connection",
        this.handleTestConnection.bind(this)
      );
    }

    // =============================================
    // GERENCIAMENTO DE ASSINATURAS
    // =============================================

    handleCancelSubscription(e) {
      e.preventDefault();

      const $button = $(e.currentTarget);
      const subscriptionId = $button.data("subscription-id");

      if (!this.confirmCancelSubscription()) {
        return;
      }

      this.disableSubscriptionButton($button);

      $.ajax({
        url: this.getAdminAjaxUrl(),
        type: "POST",
        data: {
          action: "upmkt_admin_cancel_subscription",
          subscription_id: subscriptionId,
          nonce: this.getAdminNonce(),
        },
        success: (response) => this.handleCancelSuccess(response, $button),
        error: () => this.handleCancelError($button),
      });
    }

    confirmCancelSubscription() {
      const message =
        upmkt_admin?.i18n?.confirm_cancel ||
        "Tem certeza que deseja cancelar esta doação?";
      return confirm(message);
    }

    disableSubscriptionButton($button) {
      const processingText = upmkt_admin?.i18n?.processing || "Processando...";
      $button.prop("disabled", true).text(processingText);
    }

    enableSubscriptionButton($button, text = "Cancelar") {
      $button.prop("disabled", false).text(text);
    }

    handleCancelSuccess(response, $button) {
      if (response.success) {
        this.showNotice(response.data.message, "success");
        $button.closest("tr").fadeOut();
      } else {
        const errorMessage =
          response.data?.message || "Erro ao cancelar doação";
        this.showNotice(errorMessage, "error");
        this.enableSubscriptionButton($button);
      }
    }

    handleCancelError($button) {
      this.showNotice("Erro de conexão.", "error");
      this.enableSubscriptionButton($button);
    }

    bindSubscriptionEvents() {
      $(document).on(
        "click",
        ".upmkt-cancel-subscription",
        this.handleCancelSubscription.bind(this)
      );
    }

    // =============================================
    // EXPORTAÇÃO DE DADOS
    // =============================================

    handleExport(e) {
      e.preventDefault();

      const $button = $(e.currentTarget);
      const format = $button.data("format") || "csv";

      this.disableExportButton($button);

      // Simula export - implementar AJAX real posteriormente
      setTimeout(() => {
        this.showNotice(
          "Exportação iniciada. Verifique seu e-mail.",
          "success"
        );
        this.enableExportButton($button);
      }, 1000);
    }

    disableExportButton($button) {
      $button.prop("disabled", true).text("Exportando...");
    }

    enableExportButton($button) {
      $button.prop("disabled", false).text("Exportar");
    }

    bindExportEvents() {
      $(document).on(
        "click",
        ".upmkt-export-btn",
        this.handleExport.bind(this)
      );
    }

    // =============================================
    // DETALHES E UTILITÁRIOS
    // =============================================

    toggleDetails(e) {
      e.preventDefault();

      const $trigger = $(e.currentTarget);
      const $details = $trigger.next(".upmkt-details");

      $details.slideToggle();
      $trigger.text(
        $details.is(":visible") ? "Ocultar Detalhes" : "Ver Detalhes"
      );
    }

    bindDetailsEvents() {
      $(document).on(
        "click",
        ".upmkt-toggle-details",
        this.toggleDetails.bind(this)
      );
    }

    // =============================================
    // SISTEMA DE NOTIFICAÇÕES
    // =============================================

    showNotice(message, type = "info") {
      const noticeClass = this.getNoticeClass(type);
      const $notice = this.createNoticeElement(message, noticeClass);

      $(".wrap").prepend($notice);
      this.setupNoticeAutoRemove($notice);
      this.setupNoticeDismiss($notice);
    }

    getNoticeClass(type) {
      const noticeClasses = {
        error: "notice-error",
        success: "notice-success",
        info: "notice-info",
      };
      return noticeClasses[type] || "notice-info";
    }

    createNoticeElement(message, noticeClass) {
      return $(
        `<div class="notice ${noticeClass} is-dismissible" style="margin-top: 20px;">
          <p>${message}</p>
          <button type="button" class="notice-dismiss"></button>
        </div>`
      );
    }

    setupNoticeAutoRemove($notice) {
      setTimeout(() => {
        $notice.fadeOut(() => $notice.remove());
      }, 5000);
    }

    setupNoticeDismiss($notice) {
      $notice.find(".notice-dismiss").on("click", () => {
        $notice.fadeOut(() => $notice.remove());
      });
    }

    // =============================================
    // UTILITÁRIOS
    // =============================================

    getAdminNonce() {
      return upmkt_admin?.nonce || $("#upmkt_admin_nonce").val();
    }

    getAdminAjaxUrl() {
      return upmkt_admin?.ajax_url || ajaxurl;
    }
  }

  // =============================================
  // INICIALIZAÇÃO
  // =============================================

  $(document).ready(() => {
    new UPMktAdmin();
  });
})(jQuery);
