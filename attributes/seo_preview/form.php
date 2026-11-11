<?php defined('C5_EXECUTE') or die('Access Denied.');

/** @var array $config */
$card = 'background:#fff;color:#202124;border-radius:8px;overflow:hidden;font-family:Arial,sans-serif;';
$image = 'height:130px;background:#e4e6eb center/cover no-repeat;display:flex;align-items:center;justify-content:center;color:#65676b;font-size:12px;';
?>

<div id="<?php echo h($config['id']); ?>">
    <div class="small mb-2"><?php echo h(t('Updates as you edit the page name, description, Meta Title and Meta Description in this form.')); ?></div>

    <h6 class="mb-2"><?php echo h(t('Google search result')); ?></h6>
    <div style="<?php echo $card; ?>padding:16px;max-width:600px;">
        <div data-p="crumb" style="font-size:12px;line-height:1.3;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;"></div>
        <div data-p="serpTitle" style="font-size:20px;line-height:1.3;color:#1a0dab;margin:2px 0;"></div>
        <div data-p="serpDescription" style="font-size:14px;line-height:1.58;color:#4d5156;"></div>
    </div>
    <div class="small mt-2" data-p="meters"></div>

    <h6 class="mt-3 mb-2"><?php echo h(t('Shared on social media')); ?></h6>
    <div class="row g-3">
        <div class="col-md-6">
            <div style="<?php echo $card; ?>">
                <div data-p="ogImage" style="<?php echo $image; ?>"></div>
                <div style="padding:10px;">
                    <div data-p="host" style="font-size:11px;text-transform:uppercase;color:#65676b;"></div>
                    <div data-p="ogTitle" style="font-size:15px;font-weight:600;"></div>
                    <div data-p="ogDescription" style="font-size:13px;color:#65676b;"></div>
                </div>
            </div>
            <div class="small mt-1"><?php echo h(t('Facebook, LinkedIn (Open Graph, output by core)')); ?></div>
        </div>
        <div class="col-md-6">
            <div style="<?php echo $card; ?>">
                <div data-p="twImage" style="<?php echo $image; ?>"></div>
                <div style="padding:10px;">
                    <div data-p="twTitle" style="font-size:15px;font-weight:600;"></div>
                    <div data-p="twDescription" style="font-size:13px;color:#65676b;"></div>
                </div>
            </div>
            <div class="small mt-1"><?php echo h(t('X (Twitter Card, output by SEO Kit)')); ?></div>
        </div>
    </div>
    <div class="small mt-1"><?php echo h(t('Images show the saved Thumbnail and site default.')); ?></div>

    <ul class="mt-3 mb-0 small" data-p="checks"></ul>
</div>

<script>
    (function () {
        var cfg = <?php echo json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        var root = document.getElementById(cfg.id);
        var ctx = document.createElement('canvas').getContext('2d');
        var TITLE_FONT = '20px Arial';
        var DESCRIPTION_FONT = '14px Arial';

        function part(name) {
            return root.querySelector('[data-p="' + name + '"]');
        }

        function px(text, font) {
            ctx.font = font;
            return Math.round(ctx.measureText(text).width);
        }

        // the longest start of text that fits in max pixels, with an ellipsis when cut
        function fit(text, font, max) {
            if (px(text, font) <= max) {
                return text;
            }

            var low = 0;
            var high = text.length;

            while (low < high) {
                var mid = Math.ceil((low + high) / 2);
                if (px(text.slice(0, mid).trim() + '…', font) <= max) {
                    low = mid;
                } else {
                    high = mid - 1;
                }
            }

            return text.slice(0, low).trim() + '…';
        }

        function say(template) {
            var args = Array.prototype.slice.call(arguments, 1);
            return template.replace(/%s/g, function () {
                return args.shift();
            });
        }

        // the live value of a form field, or the saved value when that field is not on the form
        function value(fieldKey) {
            var name = cfg.fields[fieldKey];
            var input = name ? document.querySelector('[name="' + name.replace(/"/g, '\\"') + '"]') : null;
            return (input ? input.value : cfg.saved[fieldKey] || '').replace(/\s+/g, ' ').trim();
        }

        function setImage(el, url) {
            el.style.backgroundImage = url ? 'url("' + url.replace(/"/g, '%22') + '")' : '';
            el.textContent = url ? '' : cfg.messages.noImage;
        }

        function host() {
            try {
                return new URL(cfg.url).host;
            } catch (e) {
                return window.location.host;
            }
        }

        function crumb() {
            try {
                var u = new URL(cfg.url);
                return u.host + u.pathname.replace(/\/+$/, '').split('/').filter(Boolean).map(function (s) { return ' › ' + s; }).join('');
            } catch (e) {
                return window.location.host;
            }
        }

        function update() {
            // the form is replaced when the panel is closed; stop listening then
            if (!document.body.contains(root)) {
                document.removeEventListener('input', update);
                document.removeEventListener('change', update);
                return;
            }

            var L = cfg.limits;
            var M = cfg.messages;
            var name = value('name');
            var description = value('description');
            var metaTitle = value('metaTitle');
            var metaDescription = value('metaDescription');

            // core: a Meta Title is used as is; otherwise the page name goes through the site title format
            var title = metaTitle || cfg.site.titleFormat.replace('%1$s', cfg.site.name).replace('%2$s', name);
            var snippet = metaDescription || description;
            var titlePx = px(title, TITLE_FONT);
            var snippetPx = px(snippet, DESCRIPTION_FONT);

            part('crumb').textContent = crumb();
            part('serpTitle').textContent = fit(title, TITLE_FONT, L.titleMaxPx);
            part('serpDescription').textContent = fit(snippet, DESCRIPTION_FONT, L.descriptionMaxPx);

            part('meters').textContent = M.title + ': ' + say(M.chars, title.length, titlePx) + ' / ' + L.titleMaxPx + ' px.  ' +
                M.description + ': ' + say(M.chars, snippet.length, snippetPx) + ' / ' + L.descriptionMaxPx + ' px.';

            part('host').textContent = host();
            part('ogTitle').textContent = name;
            part('ogDescription').textContent = fit(description, DESCRIPTION_FONT, 500);
            part('twTitle').textContent = metaTitle || name;
            part('twDescription').textContent = fit(snippet, DESCRIPTION_FONT, 500);
            setImage(part('ogImage'), cfg.images.og);
            setImage(part('twImage'), cfg.images.twitter);

            var problems = [];

            if (!metaTitle) { problems.push(M.titleDefault); }
            if (titlePx > L.titleMaxPx) { problems.push(say(M.titleLong, titlePx, L.titleMaxPx)); }
            else if (titlePx < L.titleMinPx) { problems.push(M.titleShort); }
            if (!snippet) { problems.push(M.descriptionMissing); }
            else if (snippet.length < L.descriptionMinChars) { problems.push(say(M.descriptionShort, snippet.length)); }
            else if (snippetPx > L.descriptionMaxPx) { problems.push(say(M.descriptionLong, snippetPx, L.descriptionMaxPx)); }
            if (metaDescription && description && metaDescription !== description) { problems.push(M.descriptionDiffers); }
            if (!cfg.images.og) { problems.push(M.ogNoImage); }
            if (!cfg.images.twitter) { problems.push(M.twitterNoImage); }

            var list = part('checks');
            list.textContent = '';

            (problems.length ? problems : [M.allGood]).forEach(function (text) {
                var li = document.createElement('li');
                li.textContent = text;
                list.appendChild(li);
            });
        }

        document.addEventListener('input', update);
        document.addEventListener('change', update);
        update();
    })();
</script>
