/**
 * 工程輔助 - 天氣預測分屏牆（4 / 6 / 8 分屏）
 * 分屏類型：iframe 網頁、hls 交通局實時視頻、img 氣象局圖片序列（動畫）
 */
var PMWeather = (function () {
    var CMS = 'https://cms.smg.gov.mo';
    var HLS = 'https://streaming1.dsatmacau.com/traffic/';
    /* 交通局攝影機：[cam_id, 位置, 視頻流] */
    var CAMS = [[49,"新馬路與南灣大馬路交界","m2181"],[50,"水坑尾街與南灣大馬路交界路口","m2086"],[51,"友誼大橋近港澳碼頭出口交匯處","b1015"],[52,"友誼大橋近北安入口交匯處","b1039"],[58,"外港客運碼頭 A","m1001"],[59,"外港客運碼頭 B","m1002"],[60,"北安圓形地","t1014"],[61,"關閘口岸往珠海通關車道","m1028"],[62,"亞馬喇前地","m2061"],[63,"西灣大橋澳門方向","b1312"],[64,"西灣大橋氹仔方向","b1311"],[65,"美副將大馬路與士多鳥拜斯大馬路交界","m2067"],[66,"提督馬路與高士德大馬路交界","m2063"],[67,"高偉樂街與荷蘭園大馬路交界","m2013"],[68,"新馬路與巴素打爾古街交界","m2070"],[69,"松山隧道高士德方向","t4001"],[70,"松山隧道羅理基方向","t4008"],[71,"東亞運街往東亞運大馬路方向","t1019"],[72,"奧林匹克游泳館圓形地往望德聖母灣大馬路方向","t1023"],[73,"路氹連貫公路圓形地","t1030"],[74,"新馬路(近議事亭前地)向南灣大馬路方向","m2134"],[75,"東北大馬路與黑沙環中街交界向友誼圓形地方向","m2092"],[76,"南灣大馬路與區華利前地交界","m2277"],[77,"沙梨頭海邊街與魚鰓巷交界(向新馬路方向)","m1035"],[79,"巴素打爾古街近栢港停車場出口(向火船頭街方向)","m2120"],[80,"火船頭街近11號碼頭（向巴素打爾古街方向)","m2125"],[81,"南灣大馬路與殷皇子大馬路(向八角亭方向)","m2090"],[82,"南灣大馬路與殷皇子大馬路(向殷皇子大馬路方向)","m2091"],[83,"爹美刁施拿地大馬路與魚鱗巷交界(向十六浦方向)","m1049"],[84,"比厘喇馬忌士街與貨倉巷交界(向十六浦方向)","m1064"],[85,"火船頭街（近11號碼頭）（向河邊新街方向）","m2126"],[86,"河邊新街與鹽巷交界（向媽閣方向）","m2129"],[87,"東亞運圓形地(向西灣大橋入口)","b1234"],[88,"西灣湖廣場(向西灣湖景大馬路)","m1090"],[89,"西灣大橋下層隧道(向澳門方向)","b1286"],[90,"孫逸仙大馬路(近終審法院前地)","m1086"],[91,"西灣湖廣場(向孫逸仙大馬路)","m1091"],[92,"亞馬喇前地之圓形地方向","m1121"],[93,"亞馬喇前地巴士站","m1120"],[94,"殷皇子大馬路與葡京路交界","m2062"],[95,"殷皇子大馬路與約翰四世大馬路交界","m2009"],[96,"友誼大橋近澳門端之橋峰望澳門方向","b1025"],[97,"友誼大橋近氹仔端之橋峰望氹仔方向","b1033"],[98,"孫逸仙大馬路與城市日大馬路交界","m2202"],[99,"友誼圓形地與友誼橋大馬路交界(向港珠澳大橋入口方向)","m2029"],[100,"西灣湖景大馬路(向孫逸仙大馬路方向)","b1300"],[101,"河邊新街與航海學校街交界(向媽閣方向)","m2110"],[102,"比厘喇馬忌士街與馬博士巷交界","m1067"],[103,"沙梨頭海邊街與林茂巷交界","m2116"],[104,"提督馬路與罅些喇海軍上將巷交界","m2054"],[105,"關閘廣場(向隧道方向)","m2173"],[106,"羅理基博士大馬路行車隧道往松山隧道方向","m1082"],[107,"西灣大橋中段望澳門方向","b1245"],[108,"巴坡沙大馬路與青洲大馬路交界","m2259"],[109,"友誼大橋近氹仔端出口","b1041"],[110,"馬場北大馬路與馬場東大馬路交界","m1033"],[111,"爹美刁斯拿地大馬路近栢港停車場","m1166"],[112,"馬交石斜坡與俾利喇街交界","m2051"],[113,"沙梨頭海邊街與爹美刁施拿地大馬路交界","m2179"],[114,"美副將大馬路與連勝馬路交界","m2303"],[115,"美副將大馬路與俾利喇街交界","m2252"],[116,"美副將大馬路與荷蘭園大馬路交界","m2310"],[117,"橫琴口岸澳門口岸區平台層 C","h1009"],[118,"提督馬路與高士德大馬路交界（向高士德大馬路方向）","m2065"],[119,"橫琴口岸澳門口岸區平台層 A","t1077"],[120,"橫琴口岸澳門口岸區平台層 B","t1076"],[121,"橫琴口岸澳門口岸區平台層 D","h1043"],[122,"沙梨頭南街","m2169"],[123,"青茂口岸-A","m2331"],[124,"青茂口岸-B","m2332"],[125,"黑沙環新街","m2322"],[126,"捐血中心","m1122"],[127,"沙梨頭海邊街","m2198"],[128,"蘇利安圓形地","t1053"],[129,"慕拉士大馬路","m2023"],[130,"馬六甲街停車場","m2289"],[131,"華士古停車場","m1129"],[132,"高士德與俾利喇街交界","m2084"],[133,"水坑尾","m1097"],[134,"海灣花園","t1059"],[135,"宋玉生廣場","m1125"],[136,"威尼斯人","t2016"],[138,"北安大馬路往澳門方向","t1056"],[139,"路氹連貫公路(近順榮大馬路)","t2015"],[140,"偉龍馬路與飛機場圓形地交界","t2019"],[141,"路氹連貫公路下層隧道","t4202"],[144,"馬萬祺博士大馬路（向友誼圓形地方向）","a1006"],[145,"港珠澳邊檢北大馬路","p1004"],[146,"馬揸度博士大馬路(近勞工局)","m2287"],[147,"馬揸度博士大馬路","m2208"],[148,"友誼大馬路","m1134"],[149,"友誼大馬路(向馬揸度博士大馬路方向)","m2346"],[150,"友誼橋大馬路(向氹仔方向)","m1196"],[152,"港珠澳邊檢南大馬路 (入境車道)","p1057"],[153,"港珠澳邊檢南大馬路","p1018"],[154,"港珠澳人工島往珠海通關車道A","z1001"],[155,"港珠澳人工島往澳門通關車道A","z1003"],[156,"港珠澳人工島往澳門通關車道B","z1004"],[157,"港珠澳人工島往珠海通關車道B","z1002"],[158,"鏡海大馬路","a1022"],[159,"澳門大橋近人工島澳門口岸交匯處","b1514"],[160,"澳門大橋近澳門端入口","b1406"],[161,"澳門大橋近澳門端(向氹仔方向)","b1411"],[162,"澳門大橋中段澳門往氹仔方向","b1436"],[163,"澳門大橋中段氹仔往澳門方向","b1439"],[164,"澳門大橋近氹仔端出入口","b1466"],[165,"A2橋澳門出口","a1029"],[166,"泰安大馬路與馬萬祺博士大馬路交界","a1008"],[167,"澳門橋大馬路與泰安大馬路交界","a1039"]];
    var SMG = 'https://www.smg.gov.mo/zh/';
    var P = {
        zj_typhoon: {t: '風臺路徑（浙江水利）', k: 'iframe', u: 'https://typhoon.slt.zj.gov.cn/', r: 10},
        dsat_cam: {t: '現場實況（交通局）', k: 'hls', r: 1, o: 'https://www.dsat.gov.mo/dsat/realtime.aspx'},
        smg_radar: {t: '雷達圖（氣象局）', k: 'img', s: 'radar', r: 6, o: SMG + 'radar'},
        smg_radar128: {t: '雷達圖-近距離（氣象局）', k: 'img', s: 'radar128', r: 6, o: SMG + 'radar'},
        smg_sat: {t: '衛星雲圖（氣象局）', k: 'img', s: 'MTSAT', r: 10, o: SMG + 'satellite'},
        smg_tc: {t: '熱帶氣旋信號 / 天氣警告（氣象局）', k: 'iframe', u: SMG + 'for-app/typhoon-main', r: 5, o: SMG + 'typhoon-main'},
        smg_48h: {t: '48小時風速 / 氣溫預測（氣象局）', k: 'iframe', u: SMG + 'for-app/hour48forecast', r: 30},
        windy: {t: '風場預測（Windy）', k: 'iframe', r: 30,
            u: 'https://embed.windy.com/embed2.html?lat=22.19&lon=113.54&detailLat=22.19&detailLon=113.54&zoom=7&level=surface&overlay=wind&menu=&message=true&marker=true&calendar=now&type=map&location=coordinates&metricWind=km%2Fh&metricTemp=%C2%B0C'},
        smg_llis: {t: '珠三角閃電圖（氣象局）', k: 'img', s: 'llis10', r: 6, o: SMG + 'lightning'},
        smg_ipcam: {t: '天氣照片（氣象局）', k: 'img', s: 'ipcam', r: 10, o: SMG + 'ipcam'},
        smg_mslp: {t: '海平面氣壓圖（氣象局）', k: 'img', s: 'mslpchart', r: 60, o: SMG + 'forecast'},
        nmc_typhoon: {t: '颱風網（中央氣象台）', k: 'iframe', u: 'https://www.nmc.cn/publish/typhoon/typhoon_new.html', r: 10},
        rainviewer: {t: '降雨雷達（RainViewer）', k: 'iframe', r: 10,
            u: 'https://www.rainviewer.com/map.html?loc=22.19,113.54,8&oFa=0&oC=0&oU=0&oCS=1&oF=0&oAP=1&c=1&o=83&lm=1&layer=radar&sm=1&sn=1'},
        nullschool: {t: '全球風場（earth.nullschool）', k: 'iframe', u: 'https://earth.nullschool.net/#current/wind/surface/level/orthographic=113.54,22.19,3000', r: 30},
        ventusky: {t: '降雨預測（Ventusky）', k: 'iframe', u: 'https://www.ventusky.com/?p=22.19;113.54;7&l=rain-3h', r: 30},
        custom: {t: '自定義網址', k: 'iframe', u: '', r: 10}
    };
    var ORDER = ['zj_typhoon', 'dsat_cam', 'smg_radar', 'smg_sat', 'smg_tc', 'smg_48h', 'windy', 'smg_llis',
        'smg_radar128', 'smg_ipcam', 'smg_mslp', 'nmc_typhoon', 'rainviewer', 'nullschool', 'ventusky', 'custom'];
    var DEF = {layout: 4, panes: [{p: 'zj_typhoon'}, {p: 'dsat_cam', c: 63}, {p: 'smg_radar'}, {p: 'smg_sat'},
        {p: 'smg_tc'}, {p: 'smg_48h'}, {p: 'windy'}, {p: 'smg_llis'}]};
    var hlsReady = null;

    function loadHls() {
        /* 系統的 jsObject.Expand.js 用自定義 Map 覆蓋了瀏覽器原生 Map，hls.js 需在原生 Map 下運行 */
        if (!hlsReady) hlsReady = $.ajax({url: 'resource/js/hls/hls.min.js?v=1.5.17', dataType: 'text', cache: true, global: false}).then(function (src) {
            if (window.Hls) return;
            var f = document.getElementById('pm_native_frame');
            if (!f) {
                f = document.createElement('iframe');
                f.id = 'pm_native_frame';
                f.style.display = 'none';
                document.body.appendChild(f);
            }
            (new Function('Map', src)).call(window, f.contentWindow.Map);
        });
        return hlsReady;
    }
    function cam(id) {
        for (var i = 0; i < CAMS.length; i++) if (CAMS[i][0] === id) return CAMS[i];
        return CAMS[0];
    }
    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function hm(d) { return pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds()); }
    function copy(o) { return JSON.parse(JSON.stringify(o)); }
    function norm(c) {
        c = c && c.panes ? copy(c) : copy(DEF);
        if ($.inArray(c.layout, [4, 6, 8]) < 0) c.layout = 4;
        for (var i = 0; i < 8; i++) {
            var p = c.panes[i] || DEF.panes[i];
            if (!P[p.p]) p = {p: 'custom', u: p.u || ''};
            c.panes[i] = $.extend({t: '', u: '', c: 0, r: 0}, p);
        }
        c.panes.length = 8;
        return c;
    }
    /** 分屏實際參數：預設來源 + 用戶修改的標題、網址、攝影機、刷新間隔 */
    function info(p) {
        var d = P[p.p], k = d.k;
        var x = {k: k, title: p.t || d.t, url: k === 'iframe' ? (p.u || d.u) : '', r: p.r || d.r, s: d.s, o: d.o};
        if (k === 'hls') {
            var c = cam(p.c || 63);
            x.cam = c;
            x.title = (p.t || d.t) + ' - ' + c[1];
            x.url = HLS + c[2] + '.m3u8';
        }
        if (k === 'iframe' && !x.o) x.o = x.url;
        return x;
    }

    function Pane(wall, idx, $box) {
        var self = this, timers = [], hls = null, x;
        this.stop = function () {
            $.each(timers, function (i, t) { clearInterval(t); clearTimeout(t); });
            timers = [];
            if (hls) { try { hls.destroy(); } catch (e) {} hls = null; }
            $box.find('video').each(function () { try { this.pause(); this.removeAttribute('src'); this.load(); } catch (e) {} });
        };
        function every(min, fn) { timers.push(setInterval(fn, Math.max(1, min) * 60000)); }
        function status(t) { $box.find('.wx-st').text(t); }
        function body() { return $box.find('.wx-bd'); }

        function iframe() {
            if (!x.url) { body().html('<div class="wx-msg">請點「設置」填寫網址</div>'); return; }
            var $f = $('<iframe frameborder="0" allowfullscreen="true" referrerpolicy="no-referrer-when-downgrade"/>').attr('src', x.url);
            body().empty().append($f);
            status('刷新：' + hm(new Date()));
            every(x.r, function () { $f.attr('src', x.url); status('刷新：' + hm(new Date())); });
        }
        function video() {
            var $v = $('<video muted="muted" autoplay="autoplay" playsinline="playsinline" controls="controls"/>');
            body().empty().append($v);
            var v = $v[0];
            v.muted = true;
            function start() {
                if (hls) { try { hls.destroy(); } catch (e) {} hls = null; }
                body().find('.wx-msg').remove();
                if (window.Hls && Hls.isSupported()) {
                    hls = new Hls({liveDurationInfinity: true});
                    hls.on(Hls.Events.ERROR, function (e, d) {
                        if (d && d.fatal) { body().append('<div class="wx-msg">視頻暫時無法播放，將自動重試</div>'); }
                    });
                    hls.loadSource(x.url);
                    hls.attachMedia(v);
                } else if (v.canPlayType('application/vnd.apple.mpegurl')) {
                    v.src = x.url;
                } else {
                    body().append('<div class="wx-msg">瀏覽器不支持播放實時視頻，請點「新窗口」查看</div>');
                    return;
                }
                var pr = v.play();
                if (pr && pr['catch']) pr['catch'](function () {});
                status('刷新：' + hm(new Date()));
            }
            loadHls().always(function () {
                if (!$.contains(document, v)) return;
                start();
                every(x.r, start);
            });
        }
        function images() {
            var frames = [], i = 0, playing = true, updated = '';
            var $img = $('<img alt=""/>'), $bar = $('<div class="wx-cap"/>');
            body().empty().append($img).append($bar);
            function show() {
                if (!frames.length) return;
                $img.attr('src', frames[i]);
                $bar.text('第 ' + (i + 1) + '/' + frames.length + ' 幀' + (updated ? '　氣象局更新：' + updated : '') + (playing ? '' : '　（已暫停，點擊圖片繼續）'));
            }
            function load() {
                /* 跨域讀取氣象局圖片列表（不帶本系統的請求頭，避免跨域預檢失敗） */
                var xhr = new XMLHttpRequest();
                xhr.open('GET', CMS + '/zh_TW/api/imageseries/' + x.s + '?_=' + (+new Date()));
                xhr.onload = function () {
                    var d = null;
                    try { d = JSON.parse(xhr.responseText); } catch (e) {}
                    body().find('.wx-msg').remove();
                    var s = xhr.status === 200 && d && d.series ? d.series : [];
                    if (!s.length) { body().append('<div class="wx-msg">' + (d ? '氣象局暫無圖片' : '讀取氣象局圖片失敗，將自動重試') + '</div>'); return; }
                    frames = $.map(s, function (u) { return /^https?:/.test(u) ? u : CMS + u; });
                    $.each(frames, function (j, u) { (new Image()).src = u; });
                    updated = d.updatedAt || '';
                    i = frames.length - 1;
                    show();
                    status('刷新：' + hm(new Date()));
                };
                xhr.onerror = function () {
                    body().find('.wx-msg').remove();
                    body().append('<div class="wx-msg">讀取氣象局圖片失敗，將自動重試</div>');
                };
                xhr.send();
            }
            $img.click(function () { playing = !playing; if (!playing) i = frames.length - 1; show(); });
            /* 動畫：每幀 0.6 秒，最新一幀停留 2.4 秒 */
            var hold = 0;
            timers.push(setInterval(function () {
                if (!playing || frames.length < 2) return;
                if (i === frames.length - 1 && hold++ < 3) return;
                hold = 0;
                i = (i + 1) % frames.length;
                show();
            }, 600));
            load();
            every(x.r, load);
        }
        this.build = function () {
            self.stop();
            x = info(wall.cfg.panes[idx]);
            $box.empty().attr('data-k', x.k);
            var $hd = $('<div class="wx-hd"/>').appendTo($box);
            $('<span class="wx-no"/>').text(idx + 1).appendTo($hd);
            $('<span class="wx-t"/>').text(x.title).attr('title', x.title).appendTo($hd);
            var $ops = $('<span class="wx-ops"/>').appendTo($hd);
            if (x.k === 'hls') {
                var $sel = $('<select class="wx-cam"/>').appendTo($ops);
                $.each(CAMS, function (i, c) { $('<option/>').val(c[0]).text(c[1]).appendTo($sel); });
                $sel.val(String(x.cam[0])).change(function () { wall.cfg.panes[idx].c = +this.value; wall.save(); self.build(); });
            }
            $('<a href="javascript:void(0)" class="wx-set">設置</a>').click(function () { wall.edit(idx); }).appendTo($ops);
            $('<a href="javascript:void(0)">刷新</a>').click(function () { self.build(); }).appendTo($ops);
            if (x.o) $('<a target="_blank" rel="noopener noreferrer">新窗口</a>').attr('href', x.o).appendTo($ops);
            $('<a href="javascript:void(0)" class="wx-max">放大</a>').click(function () {
                var on = !$box.hasClass('wx-full');
                wall.$grid.children('.wx-full').removeClass('wx-full').find('.wx-max').text('放大');
                $box.toggleClass('wx-full', on);
                $(this).text(on ? '還原' : '放大');
            }).appendTo($ops);
            $('<div class="wx-bd"/>').appendTo($box);
            $('<div class="wx-st"/>').appendTo($box);
            if (x.k === 'iframe') iframe(); else if (x.k === 'hls') video(); else images();
        };
    }

    function Wall(U, data) {
        var w = this;
        w.$root = $(U + '_wall');
        w.$grid = $(U + '_grid');
        w.def = norm(data.def);
        w.cfg = norm(data.mine || data.def);
        w.panes = [];
        w.save = function () {
            $.ajax({url: 'api/weather.php', type: 'POST', dataType: 'json', global: false, data: {act: 'save', cfg: JSON.stringify(w.cfg)}});
        };
        w.render = function () {
            $.each(w.panes, function (i, p) { p.stop(); });
            w.panes = [];
            w.$grid.empty().attr('class', 'wx-grid wx-l' + w.cfg.layout);
            for (var i = 0; i < w.cfg.layout; i++) {
                var p = new Pane(w, i, $('<div class="wx-pane"/>').appendTo(w.$grid));
                w.panes.push(p);
                p.build();
            }
            $(U + '_wall .wx-lay').removeClass('wx-on').filter('[data-n=' + w.cfg.layout + ']').addClass('wx-on');
        };
        w.edit = function (idx) {
            var p = w.cfg.panes[idx], $d;
            var $src, $title, $url, $cam, $r;
            function sync() {
                var d = P[$src.val()];
                $title.attr('placeholder', d.t);
                $url.closest('tr').toggle(d.k === 'iframe');
                $url.prop('readonly', $src.val() !== 'custom').val($src.val() === p.p && p.u ? p.u : (d.u || ''));
                if ($src.val() === 'custom') $url.prop('readonly', false);
                $cam.closest('tr').toggle(d.k === 'hls');
                $r.attr('placeholder', '默認 ' + d.r);
            }
            $d = PM.form({
                title: '分屏 ' + (idx + 1) + ' 設置', width: 600,
                fields: [
                    {name: 'p', label: '來源', type: 'custom', render: function ($el) {
                        $src = $('<select style="width:100%;"/>').appendTo($el);
                        $.each(ORDER, function (i, k) { $('<option/>').val(k).text(P[k].t).appendTo($src); });
                        $src.val(p.p).change(sync);
                    }, getValue: function () { return $src.val(); }},
                    {name: 't', label: '標題', type: 'custom', render: function ($el) {
                        $title = $('<input type="text" style="width:100%;" maxlength="50"/>').val(p.t).appendTo($el);
                        $('<div class="pm-form-hint">留空則使用來源名稱</div>').appendTo($el);
                    }, getValue: function () { return ''; }},
                    {name: 'u', label: '網址', type: 'custom', render: function ($el) {
                        $url = $('<input type="text" style="width:100%;" placeholder="https://"/>').appendTo($el);
                        $('<div class="pm-form-hint">選「自定義網址」可填寫任意網址；部分網站禁止嵌入其他網頁，此時請用「新窗口」打開</div>').appendTo($el);
                    }, getValue: function () { return ''; }},
                    {name: 'c', label: '攝影機', type: 'custom', render: function ($el) {
                        $cam = $('<select style="width:100%;"/>').appendTo($el);
                        $.each(CAMS, function (i, c) { $('<option/>').val(c[0]).text(c[1]).appendTo($cam); });
                        $cam.val(String(p.c || 63));
                    }, getValue: function () { return ''; }},
                    {name: 'r', label: '自動刷新（分鐘）', type: 'custom', render: function ($el) {
                        $r = $('<input type="number" min="1" max="1440" style="width:120px;"/>').val(p.r || '').appendTo($el);
                    }, getValue: function () { return ''; }}
                ],
                okText: '確定',
                beforeSubmit: function () {
                    var k = $src.val(), u = $.trim($url.val());
                    if (k === 'custom' && !/^https?:\/\/\S+$/i.test(u)) { PM.alert('請填寫以 http:// 或 https:// 開頭的網址'); return false; }
                    var r = parseInt($r.val(), 10);
                    w.cfg.panes[idx] = {p: k, t: $.trim($title.val()), u: k === 'custom' ? u : '', c: +$cam.val() || 0, r: r > 0 ? Math.min(r, 1440) : 0};
                    w.save();
                    w.panes[idx].build();
                    $d.dialog('close');
                    return false;
                }
            });
            sync();
        };
        $(U + '_wall .wx-lay').click(function () { w.cfg.layout = +$(this).attr('data-n'); w.save(); w.render(); });
        $(U + '_reload').click(function () { w.render(); });
        $(U + '_fs').click(function () {
            var el = w.$root[0];
            var fn = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen;
            if (document.fullscreenElement || document.webkitFullscreenElement) {
                (document.exitFullscreen || document.webkitExitFullscreen).call(document);
            } else if (fn) fn.call(el);
        });
        $(U + '_reset').click(function () {
            PM.confirm('確定恢復默認的分屏設置嗎？', function () {
                PM.post('api/weather.php', {act: 'reset'}, function () { w.cfg = norm(w.def); w.render(); });
            });
        });
        $(U + '_setdef').click(function () {
            PM.confirm('確定把當前分屏設置設為所有用戶的默認設置嗎？', function () {
                PM.post('api/weather.php', {act: 'save_default', cfg: JSON.stringify(w.cfg)}, function () { w.def = norm(w.cfg); });
            });
        });
        $(U + '_resetdef').click(function () {
            PM.confirm('確定清除管理員設定的默認設置，恢復系統內置默認嗎？', function () {
                PM.post('api/weather.php', {act: 'reset_default'}, function () { w.def = norm(null); });
            });
        });
        w.render();
        /* 標籤頁關閉後停止刷新與視頻播放 */
        var watch = setInterval(function () {
            if (!$.contains(document, w.$root[0])) { clearInterval(watch); $.each(w.panes, function (i, p) { p.stop(); }); }
        }, 3000);
    }

    return {
        presets: P, cams: CAMS, defaults: DEF,
        init: function (U) {
            $.getJSON('api/weather.php', {act: 'get'}, function (d) {
                if (!d || !d.ok) return PM.alert(d && d.msg ? d.msg : '讀取設置失敗');
                new Wall(U, d);
            });
        }
    };
})();
