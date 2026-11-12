<?php defined('C5_EXECUTE') or die('Access Denied.');

use SeoKit\JsonLd\Types\FormRenderer;

$id = 'seo-schema-' . bin2hex(random_bytes(4));
?>

<div id="<?php echo $id; ?>"><?php echo FormRenderer::render($base, $selected, $stored); ?></div>

<script>
    (function () {
        var root = document.getElementById(<?php echo json_encode($id); ?>);
        var rowCounter = 0; // with the timestamp, keeps new row names unique even for same-millisecond clicks
        var select = root.querySelector('[data-seo-schema-type]');

        function filled(el) {
            return el.value.trim() !== '';
        }

        // Concrete's page attributes panel does not validate attributes, so say what is missing instead.
        function warn() {
            var group = root.querySelector('[data-seo-schema-fields]:not([hidden])');

            if (!group) {
                return;
            }

            var missing = [];

            group.querySelectorAll('[data-seo-required]').forEach(function (el) {
                var row = el.closest('[data-seo-row]');
                var label = el.getAttribute('data-seo-required');

                if (el.matches('[data-seo-list]')) {
                    var any = Array.prototype.some.call(el.querySelectorAll('[data-seo-row]'), function (r) {
                        return Array.prototype.some.call(r.querySelectorAll('input, textarea'), filled);
                    });

                    if (!any) {
                        missing.push(label);
                    }
                } else if (row) {
                    // inside a repeated row: only matters once the row has been started
                    var started = Array.prototype.some.call(row.querySelectorAll('input, textarea'), filled);

                    if (started && !filled(el) && missing.indexOf(label) === -1) {
                        missing.push(label);
                    }
                } else if (!filled(el)) {
                    missing.push(label);
                }
            });

            var box = group.querySelector('[data-seo-warning]');
            box.hidden = missing.length === 0;
            box.textContent = box.getAttribute('data-seo-message') + ' ' + missing.join(', ');
        }

        // show only the fields of the chosen type
        function sync() {
            root.querySelectorAll('[data-seo-schema-fields]').forEach(function (el) {
                el.hidden = el.getAttribute('data-seo-schema-fields') !== select.value;
            });
            warn();
        }

        select.addEventListener('change', sync);
        root.addEventListener('input', warn);
        root.addEventListener('change', warn);

        root.addEventListener('click', function (event) {
            var add = event.target.closest('[data-seo-add]');
            var remove = event.target.closest('[data-seo-remove]');

            if (add) {
                var list = add.closest('[data-seo-list]');
                var html = list.querySelector('template').innerHTML.replace(/<?php echo FormRenderer::ROW_INDEX; ?>/g, 'n' + Date.now() + '_' + (rowCounter++));
                list.querySelector('[data-seo-rows]').insertAdjacentHTML('beforeend', html);
            }

            if (remove) {
                remove.closest('[data-seo-row]').remove();
            }

            warn();
        });

        warn();
    })();
</script>
