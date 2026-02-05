<script>
    $(function() {ldelim}
    $('#publicStatsSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');

    var colorPicker = document.getElementById('colorPicker');
    var colorText = document.getElementById('primaryColor');
    var colorPreview = document.getElementById('colorPreview');
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
    if (colorPreview) {ldelim}
    colorPreview.style.backgroundColor = color;
    {rdelim}

    if (colorVariants) {ldelim}
    var variants = generateVariants(color);
    if (variants) {ldelim}
    colorVariants.innerHTML =
        '<div class="color-swatch" style="background-color: ' + variants.light + ';" title="Light: ' + variants.light +
        '"></div>' +
        '<div class="color-swatch primary" style="background-color: ' + variants.primary + ';" title="Primary: ' +
        variants.primary + '"></div>' +
        '<div class="color-swatch" style="background-color: ' + variants.dark + ';" title="Dark: ' + variants.dark +
        '"></div>' +
        '<div class="color-swatch" style="background-color: ' + variants.darker + ';" title="Darker: ' + variants
        .darker + '"></div>';
    {rdelim}
    {rdelim}
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
    {rdelim});
</script>

<style>
    .color-picker-container {ldelim}
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 8px;
    {rdelim}

    #colorPicker {ldelim}
    width: 50px;
    height: 40px;
    padding: 0;
    border: 2px solid #ccc;
    border-radius: 4px;
    cursor: pointer;
    {rdelim}

    #colorPicker:hover {ldelim}
    border-color: #999;
    {rdelim}

    #primaryColor {ldelim}
    width: 90px;
    font-family: monospace;
    font-size: 14px;
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
    {rdelim}

    #colorPreview {ldelim}
    width: 40px;
    height: 40px;
    border-radius: 4px;
    border: 2px solid #ccc;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
    {rdelim}

    #colorVariants {ldelim}
    display: flex;
    gap: 4px;
    padding: 6px 10px;
    background: #f5f5f5;
    border-radius: 6px;
    border: 1px solid #e0e0e0;
    {rdelim}

    .color-swatch {ldelim}
    width: 32px;
    height: 32px;
    border-radius: 4px;
    border: 1px solid rgba(0, 0, 0, 0.1);
    cursor: help;
    transition: transform 0.15s;
    {rdelim}

    .color-swatch:hover {ldelim}
    transform: scale(1.1);
    {rdelim}

    .color-swatch.primary {ldelim}
    border: 2px solid #333;
    {rdelim}

    #resetColorBtn {ldelim}
    padding: 8px 14px;
    background: #f0f0f0;
    border: 1px solid #ccc;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    color: #555;
    transition: all 0.2s;
    {rdelim}

    #resetColorBtn:hover {ldelim}
    background: #e0e0e0;
    color: #333;
    {rdelim}

    .color-info {ldelim}
    margin-top: 10px;
    padding: 10px 12px;
    background: #f9f9f9;
    border-left: 3px solid #8b2635;
    font-size: 13px;
    color: #555;
    border-radius: 0 4px 4px 0;
    {rdelim}

    .variants-label {ldelim}
    font-size: 11px;
    color: #888;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    {rdelim}
</style>

<form class="pkp_form" id="publicStatsSettingsForm" method="post"
    action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
    {csrf}
    {include file="controllers/notification/inPlaceNotification.tpl" notificationId="publicStatsSettingsFormNotification"}

    {fbvFormArea id="publicStatsSettingsFormArea"}

    {fbvFormSection}
    <p class="pkp_help">{translate key="plugins.generic.publicStats.settings.description"}</p>
    {/fbvFormSection}

    {fbvFormSection title="plugins.generic.publicStats.settings.openAlexEmail" required=true}
    {fbvElement type="text" id="openAlexEmail" value=$openAlexEmail required=true}
    <p class="pkp_help">{translate key="plugins.generic.publicStats.settings.openAlexEmailDescription"}</p>
    {/fbvFormSection}

    {fbvFormSection title="plugins.generic.publicStats.settings.primaryColor"}
    <div class="color-picker-container">
        <input type="color" id="colorPicker" value="{$primaryColor|escape|default:'#8b2635'}" />
        <input type="text" id="primaryColor" name="primaryColor" value="{$primaryColor|escape|default:'#8b2635'}"
            maxlength="7" />
        <div id="colorPreview" style="background-color: {$primaryColor|escape|default:'#8b2635'};"></div>
        <div>
            <div class="variants-label">{translate key="plugins.generic.publicStats.settings.colorVariants"}</div>
            <div id="colorVariants"></div>
        </div>
        <button type="button" id="resetColorBtn">↺
            {translate key="plugins.generic.publicStats.settings.resetColor"}</button>
    </div>
    <p class="pkp_help">{translate key="plugins.generic.publicStats.settings.primaryColorDescription"}</p>
    <div class="color-info">
        {translate key="plugins.generic.publicStats.settings.colorAffectedElements"}
    </div>
    {/fbvFormSection}

    {/fbvFormArea}

    {fbvFormButtons}
</form>