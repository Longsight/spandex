(function($) {
    $.Spandex.DefaultHook = '{{ defaultHook }}';
    $.Spandex.MissingHook = '{{ missingHook }}';
{% for script in scripts %}
{{ script }}{% endfor %}
})(jQuery);
