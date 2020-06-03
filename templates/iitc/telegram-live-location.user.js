// ==UserScript==
// @id             iitc-plugin-telegramlocation@muken
// @name           IITC Plugin: Telegram Live Location
// @category       Layer
// @version        1.1.TAG_COMPACT_DATE.TAG_COMPACT_TIME
// @namespace      https://github.com/jonatkins/ingress-intel-total-conversion
// @updateURL      https://TAG_HOST/iitc/telegram-live-location.meta.js
// @downloadURL    https://TAG_HOST/iitc/telegram-live-location.user.js
// @description    [iitc-TAG_DATE-TAG_COMPACT_TIME] Draw markers for each user in a telegram group sharing their location
// @include        https://*.ingress.com/intel*
// @include        http://*.ingress.com/intel*
// @match          https://*.ingress.com/intel*
// @match          http://*.ingress.com/intel*
// @include        https://*.ingress.com/mission/*
// @include        http://*.ingress.com/mission/*
// @match          https://*.ingress.com/mission/*
// @match          http://*.ingress.com/mission/*
// @grant          GM_xmlhttpRequest
// @domain         TAG_DOMAIN
// ==/UserScript==

unsafeWindow.telegramLL_getLocationData = function(id, token, callback) {

    GM_xmlhttpRequest({
        method: "GET",
        url: "TAG_BASE_URL/locations.php?id="+id+"&token="+token,
        headers: {"Accept": "application/json"},
        onload: function (response) {
            console.log("We got a response...");
            var json = JSON.parse(response.responseText);
            if (json.status != "OK") {
                console.log("Unable to fetch Telegram Live Location: " + json.status);
                callback(null);
            } else {
                console.log("Response was OK");
                callback(json);
            }
        }
    });
};

function wrapper(plugin_info) {
// ensure plugin framework is there, even if iitc is not yet loaded
if(typeof window.plugin !== 'function') window.plugin = function() {};

//PLUGIN AUTHORS: writing a plugin outside of the IITC build environment? if so, delete these lines!!
//(leaving them in place might break the 'About IITC' page or break update checks)
plugin_info.buildName = 'iitc';
plugin_info.dateTimeVersion = 'TAG_COMPACT_DATE.TAG_COMPACT_TIME';
plugin_info.pluginId = 'telegram-live-location';
//END PLUGIN AUTHORS NOTE



// PLUGIN START ////////////////////////////////////////////////////////
window.TELEGRAMLL_MAX_TIME = 2 * 60 * 60;

// use own namespace for plugin
window.plugin.telegramLL = function() {};

window.plugin.telegramLL.setup = function() {
    plugin.telegramLL.layerGroup = new L.LayerGroup();
    window.addLayerGroup('Telegram Live Locations', plugin.telegramLL.layerGroup, true);

    window.plugin.telegramLL.load();
    window.plugin.telegramLL.worker();

    $('#toolbox').append('<a onclick="window.plugin.telegramLL.options();return false;" title="Manage Telegram Live Locations">Telegram Live Locations</a>');
};

window.plugin.telegramLL.save = function() {
    localStorage['telegramll-refresh_time'] = JSON.stringify(window.plugin.telegramLL.refresh_time);
    localStorage['telegramll-scale'] = JSON.stringify(window.plugin.telegramLL.scale);
    localStorage['telegramll-id'] = JSON.stringify(window.plugin.telegramLL.id);
    localStorage['telegramll-token'] = JSON.stringify(window.plugin.telegramLL.token);
};

window.plugin.telegramLL.load = function() {
    if ('telegramll-refresh_time' in localStorage)
        window.plugin.telegramLL.refresh_time = JSON.parse(localStorage['telegramll-refresh_time']);
    else
        window.plugin.telegramLL.refresh_time = 5;

    if ('telegramll-scale' in localStorage)
        window.plugin.telegramLL.scale = JSON.parse(localStorage['telegramll-scale']);
    else
        window.plugin.telegramLL.scale = 100;

    if ('telegramll-id' in localStorage)
        window.plugin.telegramLL.id = JSON.parse(localStorage['telegramll-id']);
    else
        window.plugin.telegramLL.id = "";

    if ('telegramll-token' in localStorage)
        window.plugin.telegramLL.token = JSON.parse(localStorage['telegramll-token']);
    else
        window.plugin.telegramLL.token = "";
};

window.plugin.telegramLL.worker = function() {
    setTimeout(function(){ window.plugin.telegramLL.worker(); }, (window.plugin.telegramLL.refresh_time * 1000));

    if (window.plugin.telegramLL.token != "" && window.plugin.telegramLL.id != "")
        window.telegramLL_getLocationData(window.plugin.telegramLL.id,
                                          window.plugin.telegramLL.token,
                                          window.plugin.telegramLL.newDataCallback);

};

window.plugin.telegramLL.updatescale = function() {
    $("#telegramll_scalepcnt").val($("#telegramll_scale").val());
};

window.plugin.telegramLL.submit = function() {
    var refresh = parseInt($("#telegramll_refresh_time").val());
    var scale = parseInt($("#telegramll_scale").val());
    var id = $("#telegramll_id").val();
    var token = $("#telegramll_token").val();

    if (refresh < 5) {
        alert("Refresh time can't be lower then 5 seconds!");
        return false;
    }

    window.plugin.telegramLL.refresh_time = refresh;
    window.plugin.telegramLL.scale = scale;
    window.plugin.telegramLL.id = id;
    window.plugin.telegramLL.token = token;

    window.plugin.telegramLL.save();
    return true;
};

window.plugin.telegramLL.options = function() {
    var style = '<style>.leftButton{margin-right: 200px !important;}';
    style += '.tllsdiv{padding-bottom: 10px;}</style>';

    var html = style;
    html += '<div class="tllsdiv">';
    html += '<label for="telegramll_refresh_time">Refresh time in seconds: </label></br>';
    html += '<input type="text" value="' + window.plugin.telegramLL.refresh_time +'" id="telegramll_refresh_time" style="width: 50px;">';
    html += '</div>';

    html += '<div class="tllsdiv">';
    html += '<label for="telegramll_id">ID: </label></br>';
    html += '<input type="text" value="' + window.plugin.telegramLL.id +'" id="telegramll_id" style="width: 250px">';
    html += '</div>';

    html += '<div class="tllsdiv">';
    html += '<label for="telegramll_token">Token: </label></br>';
    html += '<input type="text" value="' + window.plugin.telegramLL.token +'" id="telegramll_token" style="width: 250px">';
    html += '</div>';

    html += '<div class="tllsdiv">';
    html += '<label for="telegramll_scale">Marker Scale: </label></br>';
    html += '<input type="range" min=35 max=120 value="' + window.plugin.telegramLL.scale +'" id="telegramll_scale" style="width: 200px" ' +
            'onChange="document.getElementById(\'telegramll_scalepcnt\').innerHTML = document.getElementById(\'telegramll_scale\').value;"> ';
    html += '<label id="telegramll_scalepcnt" style="padding-left: 15px">' + window.plugin.telegramLL.scale + '</label> %';
    html += '</div>';

    dialog({
        html: html,
        id: 'plugin-telegramll-settings',
        buttons: [{
            text: "Cancel",
            class: 'leftButton',
            click: function() {
                $(this).dialog( "close" );
            }
        },
        {
            text: "Save",
            click: function() {
                if (window.plugin.telegramLL.submit())
                    $(this).dialog( "close" );
            }
        }],
        title: 'Telegram Live Locations'
    });

};

window.plugin.telegramLL.newDataCallback = function(json) {
    if (json == null) {
        if (!window.plugin.telegramLL.requestData)
            plugin.telegramLL.layerGroup.clearLayers();

        return;
    }

    plugin.telegramLL.layerGroup.clearLayers();

    if (!json.rows)
        return;

    var scale = window.plugin.telegramLL.scale / 100;
    var is1 = parseInt(72 * scale);
    var is2 = parseInt(84 * scale);
    var is3 = parseInt(is1 / 2);
    var is4 = is2 - 4;

    var row;
    for (row of json.rows) {
        console.log(row);

        var icon = L.icon({
            iconUrl:      row.icon_url,
            iconSize:     [is1, is2], // size of the icon
            iconAnchor:   [is3, is4], // point of the icon which will correspond to marker's location
        });

        var tooltip = "@" + row.username + " (" + (json.time - row.time) + " seconds ago)";

        var time = (json.time - row.time - 60) / 60 / 25;
        var alpha = 1 - time;

        if (alpha <= 0.5)
        if ((json.time - row.time) > window.TELEGRAMLL_MAX_TIME) {
            console.log("Skipping due to MAX TIME passed: " + tooltip);
            continue;
        } else
    alpha = 0.5;

        if (alpha > 1)
            alpha = 1;

        console.log("Alpha: "+alpha + ", time: " + time);
        var m = L.marker([row.lat, row.lng], {icon: icon, opacity: alpha, title: tooltip, riseOnHover: true});
        m.addTo(plugin.telegramLL.layerGroup);
    }
};

var setup = plugin.telegramLL.setup;

// PLUGIN END //////////////////////////////////////////////////////////


setup.info = plugin_info; //add the script info data to the function as a property
if(!window.bootPlugins) window.bootPlugins = [];
window.bootPlugins.push(setup);
// if IITC has already booted, immediately run the 'setup' function
if(window.iitcLoaded && typeof setup === 'function') setup();
} // wrapper end
// inject code into site context
var script = document.createElement('script');
var info = {};
if (typeof GM_info !== 'undefined' && GM_info && GM_info.script) info.script = { version: GM_info.script.version, name: GM_info.script.name, description: GM_info.script.description };
script.appendChild(document.createTextNode('('+ wrapper +')('+JSON.stringify(info)+');'));
(document.body || document.head || document.documentElement).appendChild(script);


