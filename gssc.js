var gssc_initialize_flg = false;
document.addEventListener("DOMContentLoaded", function() {
    if (gssc_initialize_flg) return;
    gssc_initialize_flg = true;
    var forms = document.getElementsByClassName("gssc-form");
    for (var i = 0; i < forms.length; i++) {
        var script = document.createElement("script");
        script.setAttribute("src", "https://checkout.stripe.com/checkout.js");
        script.className = "stripe-button";
        script.setAttribute("data-key", forms[i].dataset.key);
        script.setAttribute("data-amount", forms[i].dataset.amount);
        script.setAttribute("data-name", forms[i].dataset.name);
        script.setAttribute("data-description", forms[i].dataset.description);
        script.setAttribute("data-image", "https://stripe.com/img/documentation/checkout/marketplace.png");
        script.setAttribute("data-locale", "auto");
        script.setAttribute("data-currency", forms[i].dataset.currency);
        script.setAttribute("data-zip-code", "false");
        script.setAttribute("data-allow-remember-me", "false");
        script.setAttribute("data-label", forms[i].dataset.label);
        forms[i].appendChild(script);
    }
});