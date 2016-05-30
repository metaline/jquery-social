/*!
 * jquery.social.js
 *
 * @author Daniele De Nobili
 * @license MIT
 *
 * https://github.com/metaline/jquery-social
 */

/*jslint nomen: true, plusplus: true, passfail: true, browser: true, devel: true */
/*global jQuery */

(function ($) {
    "use strict";

    function loadScript(url, id) {
        var t = document.createElement('script'),
            s = document.getElementsByTagName('script')[0];

        if (id) {
            if (document.getElementById(id)) {
                return;
            }

            t.id = id;
        }

        t.type = 'text/javascript';
        t.async = true;
        t.src = url;

        s.parentNode.insertBefore(t, s);
    }

    function humanizeNumber(num) {
        if (num >= 1e6) {
            return (num / 1e6).toFixed(1) + 'M';
        }

        if (num >= 1e3) {
            return (num / 1e3).toFixed(1) + 'k';
        }

        return num;
    }

    function random() {
        var chars = 'abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789', r = [], i;

        for (i = 0; i < 8; i++) {
            r.push(chars[Math.floor(Math.random() * 60)]);
        }

        return r.join('');
    }

    function findAnchorTag(button) {
        var a;

        if (button.is('a')) {
            a = button;
        } else {
            a = button.find('a');

            if (a.length === 0) {
                button.children().wrapAll('<a />');
                a = button.find('> a');
            }
        }
        return a;
    }

    var pluginName = "social",
        defaults = {
            template: {
                'facebook-share': '<a href="#" class="social__link"><span class="social__icon"></span><span class="social__count">{total}</span></a>',
                'twitter': '<a href="#" class="social__link"><span class="social__icon"></span><span class="social__count">Tweet</span></a>',
                'googleplus': '<a href="#" class="social__link"><span class="social__icon"></span><span class="social__count">{total}</span></a>',
                'pinterest': '<a href="#" class="social__link"><span class="social__icon"></span><span class="social__count">{total}</span></a>',
                'linkedin': '<a href="#" class="social__link"><span class="social__icon"></span><span class="social__count">{total}</span></a>'
            },
            facebookAppId: '',
            countStatsUrl: 'count-stats.php',
            lang: 'en_US',
            enableTracking: false,
            totalShareSelector: false
        },
        networks = {
            'facebook-share': {
                load: function (plugin) {
                    if (!plugin.options.facebookAppId) {
                        throw "Il tracking degli eventi di Facebook è possibile solo impostando l’opzione 'facebookAppId'";
                    }

                    if (window.fbAsyncInit) {
                        this.render(plugin);

                        return;
                    }

                    var self = this;

                    window.fbAsyncInit = function () {
                        window.FB.init({
                            appId: plugin.options.facebookAppId,
                            xfbml: true,
                            version: 'v2.5'
                        });

                        // Facebook Comment tracking
                        if (plugin.options.enableTracking['facebook-comment']) {
                            window.FB.Event.subscribe('comment.create', function (url) {
                                plugin.tracking('Facebook', 'Comment', url, plugin.options.enableTracking['facebook-comment']);
                            });
                        }

                        self.render(plugin);
                    };

                    loadScript(
                        '//connect.facebook.net/' + plugin.options.lang + '/sdk.js',
                        'facebook-jssdk'
                    );
                },
                onClick: function (plugin) {
                    window.FB.ui(
                        {
                            method: 'share',
                            href: plugin.url
                        },
                        function (response) {
                            if (response && !response.error_message && plugin.options.enableTracking['facebook-share']) {
                                plugin.tracking(
                                    'Facebook',
                                    'Share',
                                    plugin.url,
                                    plugin.options.enableTracking['facebook-share'],
                                    true,
                                    true
                                );
                            }
                        }
                    );
                },
                render: function (plugin) {
                    if (plugin.hasShareCount('facebook-share')) {
                        $.get(
                            plugin.options.countStatsUrl,
                            {
                                url: plugin.url
                            },
                            function (response) {
                                if (!response || response.error) {
                                    plugin.renderNetwork('facebook-share', 0);

                                    console.error(response.message);

                                    return;
                                }

                                if (response.share !== undefined && response.share.facebook !== undefined) {
                                    plugin.renderNetwork('facebook-share', response.share.facebook);
                                } else {
                                    plugin.renderNetwork('facebook-share', 0);
                                }
                            }
                        );
                    } else {
                        plugin.renderNetwork('facebook-share');
                    }
                }
            },
            googleplus: {
                load: function (plugin) {
                    if (plugin.hasShareCount('googleplus')) {

                        this.loadScript(function () {
                            // http://stackoverflow.com/questions/21524077/getting-google-1-page-shares-via-ajax-hidden-api
                            var params = {
                                nolog: true,
                                id: plugin.url,
                                source: "widget",
                                userId: "@viewer",
                                groupId: "@self"
                            };

                            window.gapi.client.rpcRequest('pos.plusones.get', 'v1', params).execute(function (resp) {
                                if (resp.result && resp.result.metadata && resp.result.metadata.globalCounts) {
                                    plugin.renderNetwork('googleplus', resp.result.metadata.globalCounts.count || 0);
                                } else {
                                    plugin.renderNetwork('googleplus', 0);
                                }
                            });
                        });
                    } else {
                        plugin.renderNetwork('googleplus');
                    }
                },
                onClick: function (plugin) {
                    window.open(
                        'https://plus.google.com/share?hl=' + this.fixLang(plugin.options.lang) + '&url=' + encodeURIComponent(plugin.url),
                        '',
                        'toolbar=0, status=0, width=900, height=500'
                    );

                    if (plugin.options.enableTracking.googleplus) {
                        plugin.tracking('Google', '+1', plugin.url, plugin.options.enableTracking.googleplus, true, false);
                    }
                },
                plusoneLoader: null,
                loaderPromise: null,
                loadScript: function (callback) {
                    if (this.loaderPromise === null) {
                        this.plusoneLoader = $.Deferred();
                        this.loaderPromise = $.when(this.plusoneLoader);
                    }

                    this.loaderPromise.done(callback);

                    window._onGooglePlusLoad = function () {
                        networks.googleplus.plusoneLoader.resolve();
                    };

                    loadScript('https://apis.google.com/js/client:plusone.js?onload=_onGooglePlusLoad');
                },
                fixLang: function (lang) {
                    switch (lang) {

                    case 'en_GB': // English (UK)
                    case 'en_US': // English (US)
                    case 'fr_CA': // French (Canada)
                    case 'pt_BR': // Portuguese (Brazil)
                    case 'pt_PT': // Portuguese (Portugal)"
                    case 'zh_CN': // Simplified Chinese (China)
                    case 'zh_HK': // Traditional Chinese (Hong Kong)
                    case 'zh_TW': // Traditional Chinese (Taiwan)
                        return lang.replace('_', '-');

                    case 'es_LA': // Spanish (Latin America)
                        return 'es-419';

                    case 'he_IL': // Hebrew
                        return 'iw';

                    case 'nn_NO': // Norwegian
                        return 'no';

                    case 'tl_PH': // Filipino
                        return 'fil';

                    }

                    return lang.substr(0, 2);
                }
            },
            twitter: {
                load: function (plugin) {
                    plugin.renderNetwork('twitter');

                    if (window.twttr) {
                        return;
                    }

                    // Twitter ha un codice un po' particolare per caricare le sue api
                    window.twttr = (function (d, s, id) {
                        var js, fjs = d.getElementsByTagName(s)[0],
                            t = window.twttr || {};

                        if (d.getElementById(id)) {
                            return t;
                        }

                        js = d.createElement(s);
                        js.id = id;
                        js.src = "https://platform.twitter.com/widgets.js";
                        fjs.parentNode.insertBefore(js, fjs);

                        t._e = [];
                        t.ready = function (f) {
                            t._e.push(f);
                        };

                        return t;
                    }(document, "script", "twitter-wjs"));

                    if (plugin.options.enableTracking.twitter) {
                        window.twttr.ready(function () {
                            window.twttr.events.bind('tweet', function (event) {
                                if (!event || event.type !== 'tweet') {
                                    return;
                                }

                                plugin.tracking(
                                    'Twitter',
                                    'Tweet',
                                    event.target.baseURI,
                                    plugin.options.enableTracking.twitter,
                                    true,
                                    true
                                );
                            });
                        });
                    }
                },
                onRender: function (plugin, button) {
                    var a = findAnchorTag(button),
                        url = 'https://twitter.com/intent/tweet?url=' + encodeURIComponent(plugin.url);

                    if (plugin.title) {
                        url += '&text=' + encodeURIComponent(plugin.title);
                    }

                    if (plugin.options.twitterVia) {
                        url += '&via=' + plugin.options.twitterVia;
                    }

                    a.attr('href', url);
                }
            },
            pinterest: {
                load: function (plugin) {
                    loadScript('//assets.pinterest.com/js/pinit.js');

                    if (plugin.hasShareCount('pinterest')) {
                        var f = '_PinterestCount' + random();

                        window[f] = function (data) {
                            plugin.renderNetwork('pinterest', data.count);
                        };

                        $.getScript('http://api.pinterest.com/v1/urls/count.json?url=' +
                            encodeURIComponent(plugin.url) + '&callback=' + f);
                    } else {
                        plugin.renderNetwork('pinterest');
                    }
                },
                onRender: function (plugin, button) {
                    var a = findAnchorTag(button), url;

                    url = 'https://www.pinterest.com/pin/create/button/?url=' + encodeURIComponent(plugin.url);

                    if (plugin.title) {
                        url += '&description=' + encodeURIComponent(plugin.title);
                    }

                    a.attr('href', url);
                    a.attr('data-pin-custom', true);
                    a.attr('data-pin-do', 'buttonPin');
                },
                onClick: function (plugin) {
                    if (window.PinUtils) {
                        window.PinUtils.pinAny();
                    }

                    if (plugin.options.enableTracking.pinterest) {
                        plugin.tracking('Pinterest', 'Pin It', plugin.url, plugin.options.enableTracking.pinterest, true, true);
                    }
                }
            },
            linkedin: {
                load: function (plugin) {
                    if (plugin.hasShareCount('linkedin')) {
                        var f = '_linkedInCount' + random();

                        window[f] = function (data) {
                            plugin.renderNetwork('linkedin', data.count);
                        };

                        $.getScript('https://www.linkedin.com/countserv/count/share?url=' +
                            encodeURIComponent(plugin.url) + '&format=jsonp&callback=' + f);
                    } else {
                        plugin.renderNetwork('linkedin');
                    }
                },
                onClick: function (plugin) {
                    var url = 'https://www.linkedin.com/shareArticle?mini=true&url=' + encodeURIComponent(plugin.url);

                    if (plugin.title) {
                        url += '&title=' + encodeURIComponent(plugin.title);
                    }

                    if (plugin.text) {
                        url += '&summary=' + encodeURIComponent(plugin.text);
                    }

                    window.open(
                        url,
                        'linkedin',
                        'toolbar=no,width=550,height=550'
                    );

                    if (plugin.options.enableTracking.linkedin) {
                        plugin.tracking('LinkedIn', 'Share', plugin.url, plugin.options.enableTracking.linkedin, true, true);
                    }
                }
            }
        };

    function Plugin(element, options) {
        this.element = $(element);
        this.options = $.extend({}, defaults, options);

        this.init();
    }

    Plugin.prototype = {
        init: function () {
            var plugin = this,
                networksLength = 0;

            this.networkButtons = {};

            this.element.find('[data-network]').each(function () {
                var button = $(this),
                    name = button.data('network');

                plugin.networkButtons[name] = button;

                networksLength++;
            });

            if (!networksLength) {
                return;
            }

            this.url = this.element.data('url') || $('meta[property="og:url"]').attr('content') || document.location.href;
            this.title = this.element.data('title') || $('meta[property="og:title"]').attr('content') || document.title;
            this.text = this.element.data('text') || $('meta[property="og:description"]').attr('content') || '';

            this.initTotal();
            this.load();
        },

        load: function () {
            var plugin = this;

            $.each(this.networkButtons, function (name) {
                if (networks[name]) {
                    networks[name].load(plugin);
                }
            });
        },

        renderNetwork: function (network, total) {
            var plugin = this,
                button = this.networkButtons[network];

            button.data('share', total);

            if (networks[network]) {
                plugin.renderButton(network, total);

                if (networks[network] && typeof networks[network].onRender === 'function') {
                    networks[network].onRender(plugin, button);
                }

                button.on('click', function (event) {
                    event.preventDefault();

                    button.data('share', button.data('share') + 1);

                    plugin.renderButton(network, button.data('share'));

                    if (typeof networks[network].onClick === 'function') {
                        networks[network].onClick(plugin, button);
                    }
                });
            }
        },

        renderButton: function (network, total) {
            this.networkButtons[network].html(
                this.options.template[network].replace('{total}', humanizeNumber(total))
            );

            this.incrementTotal(total);
        },

        hasShareCount: function (network) {
            return this.options.template[network].indexOf('{total}') !== -1;
        },

        tracking: function (network, event, url, value, e, s) {
            if (window.ga) {
                if (e === undefined || e) {
                    // https://developers.google.com/analytics/devguides/collection/analyticsjs/events
                    window.ga('send', 'event', 'Social', event + ' (' + network + ')', url, value);
                }

                if (s === undefined || s) {
                    // https://developers.google.com/analytics/devguides/collection/analyticsjs/social-interactions
                    window.ga('send', 'social', network, event, url, value);
                }
            }
        },

        initTotal: function () {
            if (this.options.totalShareSelector) {
                this.$totalShare = $(this.options.totalShareSelector);
            }

            if (!this.$totalShare) {
                return;
            }

            this.total = 0;
            this.$totalShare.text(this.total);
        },

        incrementTotal: function (total) {
            if (!this.$totalShare || !total) {
                return;
            }

            this.total += total;
            this.$totalShare.text(humanizeNumber(this.total));
        }
    };

    $.fn[pluginName] = function (options) {
        return this.each(function () {
            if (!$.data(this, "plugin_" + pluginName)) {
                $.data(this, "plugin_" + pluginName, new Plugin(this, options));
            }
        });
    };

}(jQuery));
