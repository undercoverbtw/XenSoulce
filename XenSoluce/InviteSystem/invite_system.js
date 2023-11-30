var XSIS = window.XSIS || {};

!function($, window, document, _undefined)
{
    "use strict";

    XSIS.XSTooltipCopy = XF.Element.newHandler({
        options: {
            copyText: '',
            copy : false
        },

        tooltip: null,
        copy : null,
        init: function() {

            let target = this.$target;

            this.tooltip = new XF.Tooltip(target)

            this.copy = new XF.CopyToClipboard(target, {
                copyText : this.options.copyText
            })

            if(this.options.copy)
            {
                this.tooltip.init()
                this.copy.init()
            }

        },
    });

    XF.Element.register('xs-tooltip-copy', 'XSIS.XSTooltipCopy');
}
(jQuery, window, document);
