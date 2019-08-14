(function() {
    function riskifiedBeaconLoad() {
        var url = ('https:' == document.location.protocol ? 'https://' : 'http://')
            + "beacon.riskified.com?shop=" + riskified_store_domain + "&sid=" + riskified_session_id;
        var s = document.createElement('script');
        s.type = 'text/javascript';
        s.async = true;
        s.src = url;
        var x = document.getElementsByTagName('script')[0];
        x.parentNode.insertBefore(s, x);
    }
    if (window.attachEvent)
        window.attachEvent('onload', riskifiedBeaconLoad)
    else
        window.addEventListener('load', riskifiedBeaconLoad, false);
})();
