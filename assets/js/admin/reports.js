jQuery(function(t){var i;t("li[data-toggle=tooltip]").tooltip(),t(".input-daterange").datepicker({autoclose:!0,todayHighlight:!0,container:"#datepicker",orientation:"top left",todayBtn:"linked"}),i=t(".chart-widget").click(function(){return t(this).find(".content").slideDown(500),i.not(this).find(".content").slideUp(500)})});