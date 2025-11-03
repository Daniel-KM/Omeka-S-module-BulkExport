'use strict';

(function() {
    $(document).ready(function() {

        $(document).on('click', 'summary.bulk-export-summary', function() {
            this.setAttribute('aria-expanded', this.getAttribute('aria-expanded') === 'true' ? 'false' : 'true');
        });

    });
})();
