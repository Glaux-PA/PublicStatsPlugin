<script>
    $(function() {ldelim}
    $('#publicStatsSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');

    var colorPicker = document.getElementById('colorPicker');
    var colorText = document.getElementById('primaryColor');
    var colorVariants = document.getElementById('colorVariants');
    var resetBtn = document.getElementById('resetColorBtn');
    var defaultColor = '{$defaultPrimaryColor|escape:"javascript"}';

    function hexToRgb(hex) {ldelim}
    hex = hex.replace('#', '');
    if (hex.length === 3) {ldelim}
    hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
    {rdelim}
    var r = parseInt(hex.substring(0, 2), 16);
    var g = parseInt(hex.substring(2, 4), 16);
    var b = parseInt(hex.substring(4, 6), 16);
    return {ldelim} r: r, g: g, b: b {rdelim};
    {rdelim}

    function rgbToHex(r, g, b) {ldelim}
    return '#' + [r, g, b].map(function(x) {ldelim}
    var hex = Math.round(Math.max(0, Math.min(255, x))).toString(16);
    return hex.length === 1 ? '0' + hex : hex;
    {rdelim}).join('');
    {rdelim}

    function generateVariants(hex) {ldelim}
    var rgb = hexToRgb(hex);
    if (!rgb) return null;

    return {ldelim}
    light: rgbToHex(rgb.r * 1.12, rgb.g * 1.12, rgb.b * 1.12),
        primary: hex,
        dark: rgbToHex(rgb.r * 0.77, rgb.g * 0.77, rgb.b * 0.77),
        darker: rgbToHex(rgb.r * 0.65, rgb.g * 0.65, rgb.b * 0.65)
    {rdelim};
    {rdelim}

    function updatePreview(color) {ldelim}
        if (!colorVariants) return;
        var variants = generateVariants(color);
        if (!variants) return;
        colorVariants.innerHTML =
            '<span class="ps-color-swatch" style="background-color: ' + variants.light + ';" title="Light: ' + variants.light + '"></span>' +
            '<span class="ps-color-swatch primary" style="background-color: ' + variants.primary + ';" title="Primary: ' + variants.primary + '"></span>' +
            '<span class="ps-color-swatch" style="background-color: ' + variants.dark + ';" title="Dark: ' + variants.dark + '"></span>' +
            '<span class="ps-color-swatch" style="background-color: ' + variants.darker + ';" title="Darker: ' + variants.darker + '"></span>';
    {rdelim}

    if (colorPicker && colorText) {ldelim}
    colorPicker.addEventListener('input', function() {ldelim}
    colorText.value = this.value;
    updatePreview(this.value);
    {rdelim});

    colorText.addEventListener('input', function() {ldelim}
    if (/^#[0-9A-Fa-f]{ldelim}6{rdelim}$/.test(this.value)) {ldelim}
    colorPicker.value = this.value;
    updatePreview(this.value);
    {rdelim}
    {rdelim});

    updatePreview(colorText.value || defaultColor);
    {rdelim}

    if (resetBtn) {ldelim}
    resetBtn.addEventListener('click', function(e) {ldelim}
    e.preventDefault();
    colorText.value = defaultColor;
    colorPicker.value = defaultColor;
    updatePreview(defaultColor);
    {rdelim});
    {rdelim}

    // ---- Section accordion ----
    function updateGroupCheck(groupId) {ldelim}
    var subs = document.querySelectorAll('.ps-sub-check[data-group="' + groupId + '"]');
    var checked = Array.from(subs).filter(function(s) {ldelim} return s.checked; {rdelim}).length;
    var cb = document.querySelector('.ps-group-check[data-group="' + groupId + '"]');
    if (!cb) return;
    cb.indeterminate = false;
    if (checked === 0) {ldelim} cb.checked = false; {rdelim}
    else if (checked === subs.length) {ldelim} cb.checked = true; {rdelim}
    else {ldelim} cb.indeterminate = true; {rdelim}
    {rdelim}

    document.querySelectorAll('.ps-group-check').forEach(function(cb) {ldelim}
    var g = cb.dataset.group;
    updateGroupCheck(g);
    cb.addEventListener('click', function() {ldelim}
    document.querySelectorAll('.ps-sub-check[data-group="' + g + '"]')
    .forEach(function(s) {ldelim} s.checked = cb.checked; {rdelim});
    {rdelim});
    {rdelim});

    document.querySelectorAll('.ps-sub-check').forEach(function(sub) {ldelim}
    sub.addEventListener('change', function() {ldelim} updateGroupCheck(sub.dataset.group); {rdelim});
    {rdelim});

    document.querySelectorAll('.ps-toggle-btn').forEach(function(btn) {ldelim}
    btn.addEventListener('click', function() {ldelim}
    var grid = document.getElementById('ps-subs-' + btn.dataset.group);
    var open = btn.getAttribute('aria-expanded') === 'true';
    grid.style.display = open ? 'none' : 'grid';
    btn.setAttribute('aria-expanded', !open);
    btn.textContent = open ? '›' : '▾';
    {rdelim});
    {rdelim});
    {rdelim});
</script>

<style>
    /* ---- Color picker ---- */
    .ps-color-picker {ldelim}
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 4px;
    {rdelim}
    .ps-color-picker input[type="color"] {ldelim}
        width: 40px;
        height: 32px;
        padding: 0;
        border: 1px solid #ccc;
        cursor: pointer;
    {rdelim}
    .ps-color-picker input[type="text"] {ldelim}
        width: 92px;
        font-family: monospace;
    {rdelim}
    .ps-color-variants {ldelim}
        display: inline-flex;
        gap: 3px;
        align-items: center;
    {rdelim}
    .ps-color-swatch {ldelim}
        width: 22px;
        height: 22px;
        border: 1px solid rgba(0, 0, 0, 0.15);
    {rdelim}
    .ps-color-swatch.primary {ldelim} outline: 1px solid #555; {rdelim}

    /* ---- Section accordion (PKP-flat) ---- */
    .ps-sections-editor {ldelim}
        display: flex;
        flex-direction: column;
        margin-top: 6px;
        border-top: 1px solid #e7e7e7;
    {rdelim}
    .ps-group-block {ldelim}
        border-bottom: 1px solid #e7e7e7;
    {rdelim}
    .ps-group-bar {ldelim}
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 2px;
    {rdelim}
    .ps-group-check {ldelim}
        flex-shrink: 0;
        margin: 0;
    {rdelim}
    .ps-group-title {ldelim}
        flex: 1;
        font-size: 13px;
        color: #333;
    {rdelim}
    .ps-toggle-btn {ldelim}
        background: none;
        border: none;
        padding: 0 6px;
        cursor: pointer;
        color: #888;
        font-size: 16px;
        line-height: 1;
    {rdelim}
    .ps-toggle-btn:hover {ldelim} color: #333; {rdelim}
    .ps-subs-grid {ldelim}
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 4px 16px;
        padding: 4px 0 10px 22px;
    {rdelim}
    .ps-sub-label {ldelim}
        display: flex !important;
        align-items: center;
        gap: 6px;
        font-size: 12px !important;
        line-height: 1.3 !important;
        color: #555 !important;
        font-weight: normal !important;
        cursor: pointer;
        text-transform: none !important;
    {rdelim}
    .ps-sub-label span {ldelim}
        font-size: 12px !important;
        font-weight: normal !important;
        color: #555 !important;
        text-transform: none !important;
    {rdelim}
    .ps-sub-label input[type="checkbox"] {ldelim}
        flex-shrink: 0;
        margin: 0;
    {rdelim}

    /* ---- Intro paragraph spacing ---- */
    .ps-form-intro {ldelim}
        margin: 4px 0 14px 0;
    {rdelim}
</style>

<form class="pkp_form" id="publicStatsSettingsForm" method="post"
    action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
    {csrf}
    {include file="controllers/notification/inPlaceNotification.tpl" notificationId="publicStatsSettingsFormNotification"}

    <p class="pkp_help ps-form-intro">{translate key="plugins.generic.publicStats.settings.description"}</p>

    {fbvFormArea id="publicStatsSettingsFormArea"}

    {fbvFormSection title="plugins.generic.publicStats.settings.openAlexEmail"}
    {fbvElement type="text" id="openAlexEmail" value=$openAlexEmail}
    <p class="pkp_help">{translate key="plugins.generic.publicStats.settings.openAlexEmailDescription"}</p>
    {/fbvFormSection}

    {fbvFormSection title="plugins.generic.publicStats.settings.enabledSections"}
    <p class="pkp_help">{translate key="plugins.generic.publicStats.settings.enabledSectionsDescription"}</p>
    <div class="ps-sections-editor">
        {foreach from=$subsections key=groupId item=groupSections}
            <div class="ps-group-block">
                <div class="ps-group-bar">
                    <input type="checkbox" class="ps-group-check" data-group="{$groupId|escape}"
                        title="{translate key=$sectionGroups[$groupId]}" />
                    <span class="ps-group-title">{translate key=$sectionGroups[$groupId]}</span>
                    <button type="button" class="ps-toggle-btn" data-group="{$groupId|escape}" aria-expanded="false"
                        title="{translate key='plugins.generic.publicStats.settings.toggleSubsections'}">›</button>
                </div>
                <div class="ps-subs-grid" id="ps-subs-{$groupId|escape}" style="display:none">
                    {foreach from=$groupSections key=sectionId item=labelKey}
                        <label class="ps-sub-label">
                            <input type="checkbox" class="ps-sub-check" name="enabledSubsections[]" value="{$sectionId|escape}"
                                data-group="{$groupId|escape}" {if in_array($sectionId, $enabledSubsections)}checked{/if} />
                            <span>{translate key=$labelKey}</span>
                        </label>
                    {/foreach}
                </div>
            </div>
        {/foreach}
    </div>
    {/fbvFormSection}

    {fbvFormSection title="plugins.generic.publicStats.settings.primaryColor"}
    <div class="ps-color-picker">
        <input type="color" id="colorPicker" value="{$primaryColor|escape|default:'#8b2635'}" />
        <input type="text" id="primaryColor" name="primaryColor" value="{$primaryColor|escape|default:'#8b2635'}"
            maxlength="7" />
        <span class="ps-color-variants" id="colorVariants" aria-label="{translate key='plugins.generic.publicStats.settings.colorVariants'}"></span>
        <button type="button" class="pkp_button" id="resetColorBtn">{translate key="plugins.generic.publicStats.settings.resetColor"}</button>
    </div>
    <p class="pkp_help">{translate key="plugins.generic.publicStats.settings.primaryColorDescription"}</p>
    <div class="pkp_notification">
        {include file="controllers/notification/inPlaceNotificationContent.tpl"
            notificationId="publicStatsColorAffectedElements"
            notificationStyleClass="notifyInfo"
            notificationContents="plugins.generic.publicStats.settings.colorAffectedElements"|translate}
    </div>
    {/fbvFormSection}

    {/fbvFormArea}

    {fbvFormButtons}
</form>