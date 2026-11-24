/**
 * 假期數據：澳門公眾假期 + 中國內地節假日。
 * 農曆 / 節氣按萬年曆算法推算（1901–2100）；中國內地 2025、2026 年採用國務院辦公廳公布的放假安排，
 * 其餘年份只標出法定節日當日（每年的調休安排需另行公布）。
 * 用法：PMHoliday.get('2026-10-01') → {mo: {n, s, t} | null, cn: {n, s, legal} | null}
 */
var PMHoliday = (function () {
    /* 農曆表 / 節氣表（公共農曆算法庫 calendar.js） */
    var LUNAR_INFO = [0x04bd8, 0x04ae0, 0x0a570, 0x054d5, 0x0d260, 0x0d950, 0x16554, 0x056a0, 0x09ad0, 0x055d2, 0x04ae0, 0x0a5b6, 0x0a4d0, 0x0d250, 0x1d255, 0x0b540, 0x0d6a0, 0x0ada2, 0x095b0, 0x14977, 0x04970, 0x0a4b0, 0x0b4b5, 0x06a50, 0x06d40, 0x1ab54, 0x02b60, 0x09570, 0x052f2, 0x04970, 0x06566, 0x0d4a0, 0x0ea50, 0x16a95, 0x05ad0, 0x02b60, 0x186e3, 0x092e0, 0x1c8d7, 0x0c950, 0x0d4a0, 0x1d8a6, 0x0b550, 0x056a0, 0x1a5b4, 0x025d0, 0x092d0, 0x0d2b2, 0x0a950, 0x0b557, 0x06ca0, 0x0b550, 0x15355, 0x04da0, 0x0a5b0, 0x14573, 0x052b0, 0x0a9a8, 0x0e950, 0x06aa0, 0x0aea6, 0x0ab50, 0x04b60, 0x0aae4, 0x0a570, 0x05260, 0x0f263, 0x0d950, 0x05b57, 0x056a0, 0x096d0, 0x04dd5, 0x04ad0, 0x0a4d0, 0x0d4d4, 0x0d250, 0x0d558, 0x0b540, 0x0b6a0, 0x195a6, 0x095b0, 0x049b0, 0x0a974, 0x0a4b0, 0x0b27a, 0x06a50, 0x06d40, 0x0af46, 0x0ab60, 0x09570, 0x04af5, 0x04970, 0x064b0, 0x074a3, 0x0ea50, 0x06b58, 0x05ac0, 0x0ab60, 0x096d5, 0x092e0, 0x0c960, 0x0d954, 0x0d4a0, 0x0da50, 0x07552, 0x056a0, 0x0abb7, 0x025d0, 0x092d0, 0x0cab5, 0x0a950, 0x0b4a0, 0x0baa4, 0x0ad50, 0x055d9, 0x04ba0, 0x0a5b0, 0x15176, 0x052b0, 0x0a930, 0x07954, 0x06aa0, 0x0ad50, 0x05b52, 0x04b60, 0x0a6e6, 0x0a4e0, 0x0d260, 0x0ea65, 0x0d530, 0x05aa0, 0x076a3, 0x096d0, 0x04afb, 0x04ad0, 0x0a4d0, 0x1d0b6, 0x0d250, 0x0d520, 0x0dd45, 0x0b5a0, 0x056d0, 0x055b2, 0x049b0, 0x0a577, 0x0a4b0, 0x0aa50, 0x1b255, 0x06d20, 0x0ada0, 0x14b63, 0x09370, 0x049f8, 0x04970, 0x064b0, 0x168a6, 0x0ea50, 0x06aa0, 0x1a6c4, 0x0aae0, 0x092e0, 0x0d2e3, 0x0c960, 0x0d557, 0x0d4a0, 0x0da50, 0x05d55, 0x056a0, 0x0a6d0, 0x055d4, 0x052d0, 0x0a9b8, 0x0a950, 0x0b4a0, 0x0b6a6, 0x0ad50, 0x055a0, 0x0aba4, 0x0a5b0, 0x052b0, 0x0b273, 0x06930, 0x07337, 0x06aa0, 0x0ad50, 0x14b55, 0x04b60, 0x0a570, 0x054e4, 0x0d160, 0x0e968, 0x0d520, 0x0daa0, 0x16aa6, 0x056d0, 0x04ae0, 0x0a9d4, 0x0a2d0, 0x0d150, 0x0f252, 0x0d520];
    var TERM_INFO = '9778397bd097c36b0b6fc9274c91aa97b6b97bd19801ec9210c965cc920e97bcf97c3598082c95f8c965cc920f97bd0b06bdb0722c965ce1cfcc920fb027097bd097c36b0b6fc9274c91aa97b6b97bd19801ec9210c965cc920e97bcf97c359801ec95f8c965cc920f97bd0b06bdb0722c965ce1cfcc920fb027097bd097c36b0b6fc9274c91aa97b6b97bd19801ec9210c965cc920e97bcf97c359801ec95f8c965cc920f97bd0b06bdb0722c965ce1cfcc920fb027097bd097c36b0b6fc9274c91aa9778397bd19801ec9210c965cc920e97b6b97bd19801ec95f8c965cc920f97bd09801d98082c95f8e1cfcc920f97bd097bd097c36b0b6fc9210c8dc29778397bd197c36c9210c9274c91aa97b6b97bd19801ec95f8c965cc920e97bd09801d98082c95f8e1cfcc920f97bd097bd097c36b0b6fc9210c8dc29778397bd097c36c9210c9274c91aa97b6b97bd19801ec95f8c965cc920e97bcf97c3598082c95f8e1cfcc920f97bd097bd097c36b0b6fc9210c8dc29778397bd097c36c9210c9274c91aa97b6b97bd19801ec9210c965cc920e97bcf97c3598082c95f8c965cc920f97bd097bd097c35b0b6fc920fb07229778397bd097c36b0b6fc9274c91aa97b6b97bd19801ec9210c965cc920e97bcf97c3598082c95f8c965cc920f97bd097bd097c35b0b6fc920fb07229778397bd097c36b0b6fc9274c91aa97b6b97bd19801ec9210c965cc920e97bcf97c359801ec95f8c965cc920f97bd097bd097c35b0b6fc920fb07229778397bd097c36b0b6fc9274c91aa97b6b97bd19801ec9210c965cc920e97bcf97c359801ec95f8c965cc920f97bd097bd097c35b0b6fc920fb07229778397bd097c36b0b6fc9274c91aa97b6b97bd19801ec9210c965cc920e97bcf97c359801ec95f8c965cc920f97bd097bd07f595b0b6fc920fb07229778397bd097c36b0b6fc9210c8dc29778397bd19801ec9210c9274c920e97b6b97bd19801ec95f8c965cc920f97bd07f5307f595b0b0bc920fb07227f0e397bd097c36b0b6fc9210c8dc29778397bd097c36c9210c9274c920e97b6b97bd19801ec95f8c965cc920f97bd07f5307f595b0b0bc920fb07227f0e397bd097c36b0b6fc9210c8dc29778397bd097c36c9210c9274c91aa97b6b97bd19801ec9210c965cc920e97bd07f1487f595b0b0bc920fb07227f0e397bd097c36b0b6fc9210c8dc29778397bd097c36b0b6fc9274c91aa97b6b97bd19801ec9210c965cc920e97bcf7f1487f595b0b0bb0b6fb07227f0e397bd097c35b0b6fc920fb07229778397bd097c36b0b6fc9274c91aa97b6b97bd19801ec9210c965cc920e97bcf7f1487f595b0b0bb0b6fb07227f0e397bd097c35b0b6fc920fb07229778397bd097c36b0b6fc9274c91aa97b6b97bd19801ec9210c965cc920e97bcf7f1487f531b0b0bb0b6fb07227f0e397bd097c35b0b6fc920fb07229778397bd097c36b0b6fc9274c91aa97b6b97bd19801ec9210c965cc920e97bcf7f1487f531b0b0bb0b6fb07227f0e397bd07f595b0b6fc920fb07229778397bd097c36b0b6fc9274c91aa97b6b97bd19801ec9210c9274c920e97bcf7f0e47f531b0b0bb0b6fb07227f0e397bd07f595b0b0bc920fb07229778397bd097c36b0b6fc9210c91aa97b6b97bd197c36c9210c9274c920e97bcf7f0e47f531b0b0bb0b6fb07227f0e397bd07f595b0b0bc920fb07229778397bd097c36b0b6fc9210c8dc29778397bd097c36c9210c9274c920e97b6b7f0e47f531b0723b0b6fb07227f0e37f5307f595b0b0bc920fb07227f0e397bd097c36b0b6fc9210c8dc29778397bd097c36b0b70c9274c91aa97b6b7f0e47f531b0723b0b6fb07217f0e37f1487f595b0b0bb0b6fb07227f0e397bd097c35b0b6fc9210c8dc29778397bd097c36b0b6fc9274c91aa97b6b7f0e47f531b0723b0b6fb07217f0e27f1487f595b0b0bb0b6fb07227f0e397bd097c35b0b6fc920fb07229778397bd097c36b0b6fc9274c91aa97b6b7f0e47f531b0723b0b6fb07217f0e27f1487f531b0b0bb0b6fb07227f0e397bd097c35b0b6fc920fb07229778397bd097c36b0b6fc9274c91aa97b6b7f0e47f531b0723b0b6fb07217f0e27f1487f531b0b0bb0b6fb07227f0e397bd097c35b0b6fc920fb07229778397bd097c36b0b6fc9274c91aa97b6b7f0e47f531b0723b0b6fb07217f0e27f1487f531b0b0bb0b6fb07227f0e397bd07f595b0b0bc920fb07229778397bd097c36b0b6fc9274c91aa97b6b7f0e47f531b0723b0787b07217f0e27f0e47f531b0b0bb0b6fb07227f0e397bd07f595b0b0bc920fb07229778397bd097c36b0b6fc9210c91aa97b6b7f0e47f149b0723b0787b07217f0e27f0e47f531b0723b0b6fb07227f0e397bd07f595b0b0bc920fb07229778397bd097c36b0b6fc9210c8dc2977837f0e37f149b0723b0787b07217f07e7f0e47f531b0723b0b6fb07227f0e37f5307f595b0b0bc920fb07227f0e397bd097c35b0b6fc9210c8dc2977837f0e37f14998082b0787b07217f07e7f0e47f531b0723b0b6fb07217f0e37f1487f595b0b0bb0b6fb07227f0e397bd097c35b0b6fc9210c8dc2977837f0e37f14998082b0787b06bd7f07e7f0e47f531b0723b0b6fb07217f0e27f1487f531b0b0bb0b6fb07227f0e397bd097c35b0b6fc920fb0722977837f0e37f14998082b0787b06bd7f07e7f0e47f531b0723b0b6fb07217f0e27f1487f531b0b0bb0b6fb07227f0e397bd097c35b0b6fc920fb0722977837f0e37f14998082b0787b06bd7f07e7f0e47f531b0723b0b6fb07217f0e27f1487f531b0b0bb0b6fb07227f0e397bd07f595b0b0bc920fb0722977837f0e37f14998082b0787b06bd7f07e7f0e47f531b0723b0b6fb07217f0e27f1487f531b0b0bb0b6fb07227f0e397bd07f595b0b0bc920fb0722977837f0e37f14998082b0787b06bd7f07e7f0e47f149b0723b0787b07217f0e27f0e47f531b0b0bb0b6fb07227f0e397bd07f595b0b0bc920fb0722977837f0e37f14998082b0723b06bd7f07e7f0e37f149b0723b0787b07217f0e27f0e47f531b0723b0b6fb07227f0e397bd07f595b0b0bc920fb0722977837f0e37f14898082b0723b02d57ec967f0e37f14998082b0787b07217f07e7f0e47f531b0723b0b6fb07227f0e37f1487f595b0b0bb0b6fb07227f0e37f0e37f14898082b0723b02d57ec967f0e37f14998082b0787b07217f07e7f0e47f531b0723b0b6fb07227f0e37f1487f531b0b0bb0b6fb07227f0e37f0e37f14898082b0723b02d57ec967f0e37f14998082b0787b06bd7f07e7f0e47f531b0723b0b6fb07217f0e37f1487f531b0b0bb0b6fb07227f0e37f0e37f14898082b072297c357ec967f0e37f14998082b0787b06bd7f07e7f0e47f531b0723b0b6fb07217f0e27f1487f531b0b0bb0b6fb07227f0e37f0e37f14898082b072297c357ec967f0e37f14998082b0787b06bd7f07e7f0e47f531b0723b0b6fb07217f0e27f1487f531b0b0bb0b6fb07227f0e37f0e366aa89801eb072297c357ec967f0e37f14998082b0787b06bd7f07e7f0e47f149b0723b0787b07217f0e27f1487f531b0b0bb0b6fb07227f0e37f0e366aa89801eb072297c357ec967f0e37f14998082b0723b06bd7f07e7f0e47f149b0723b0787b07217f0e27f0e47f531b0723b0b6fb07227f0e37f0e366aa89801eb072297c357ec967f0e37f14998082b0723b06bd7f07e7f0e37f14998083b0787b07217f0e27f0e47f531b0723b0b6fb07227f0e37f0e366aa89801eb072297c357ec967f0e37f14898082b0723b02d57f07e7f0e37f14998082b0787b07217f07e7f0e47f531b0723b0b6fb07227f0e36665b66aa89801e9808297c35665f67f0e37f14898082b0723b02d57ec967f0e37f14998082b0787b07217f07e7f0e47f531b0723b0b6fb07227f0e36665b66a449801e9808297c35665f67f0e37f14898082b0723b02d57ec967f0e37f14998082b0787b06bd7f07e7f0e47f531b0723b0b6fb07217f0e36665b66a449801e9808297c35665f67f0e37f14898082b072297c357ec967f0e37f14998082b0787b06bd7f07e7f0e47f531b0723b0b6fb07217f0e26665b66a449801e9808297c35665f67f0e37f1489801eb072297c357ec967f0e37f14998082b0787b06bd7f07e7f0e47f531b0723b0b6fb07217f0e27f1487f531b0b0bb0b6fb0722';

    function lYearDays(y) {
        var i, sum = 348;
        for (i = 0x8000; i > 0x8; i >>= 1) sum += (LUNAR_INFO[y - 1900] & i) ? 1 : 0;
        return sum + leapDays(y);
    }
    function leapMonth(y) { return LUNAR_INFO[y - 1900] & 0xf; }
    function leapDays(y) { if (leapMonth(y)) return (LUNAR_INFO[y - 1900] & 0x10000) ? 30 : 29; return 0; }
    function monthDays(y, m) { if (m > 12 || m < 1) return -1; return (LUNAR_INFO[y - 1900] & (0x10000 >> m)) ? 30 : 29; }
    function getTerm(y, n) {
        if (y < 1900 || y > 2100 || n < 1 || n > 24) return -1;
        var table = TERM_INFO.substr((y - 1900) * 30, 30);
        var calc = [];
        for (var i = 0; i < table.length; i += 5) {
            var chunk = parseInt('0x' + table.substr(i, 5)).toString();
            calc.push(chunk[0], chunk.substr(1, 2), chunk[3], chunk.substr(4, 2));
        }
        return parseInt(calc[n - 1]);
    }
    function addDays(ts, n) { var d = new Date(ts); d.setUTCDate(d.getUTCDate() + n); return d; }
    function fmt(d) { return d.getUTCFullYear() + '-' + ('0' + (d.getUTCMonth() + 1)).slice(-2) + '-' + ('0' + d.getUTCDate()).slice(-2); }
    function lunar2solar(y, lm, ld) {
        if (y < 1901 || y > 2100) return null;
        var off = 0, i;
        for (i = 1900; i < y; i++) off += lYearDays(i);
        var leap = leapMonth(y);
        for (i = 1; i < lm; i++) {
            off += monthDays(y, i);
            if (leap > 0 && i === leap) off += leapDays(y);
        }
        off += ld - 1;
        return addDays(Date.UTC(1900, 0, 31), off);
    }
    function easterSunday(y) {
        var a = y % 19, b = Math.floor(y / 100), c = y % 100,
            d = Math.floor(b / 4), e = b % 4,
            f = Math.floor((b + 8) / 25), g = Math.floor((b - f + 1) / 3),
            h = (19 * a + b - d - g + 15) % 30,
            i2 = Math.floor(c / 4), k = c % 4,
            l = (32 + 2 * e + 2 * i2 - h - k) % 7,
            m = Math.floor((a + 11 * h + 22 * l) / 451),
            mo = Math.floor((h + l - 7 * m + 114) / 31),
            da = ((h + l - 7 * m + 114) % 31) + 1;
        return new Date(Date.UTC(y, mo - 1, da));
    }

    var moCache = {}, cnCache = {};
    /** 澳門公眾假期：t 為「強制性」或「非強制性」 */
    function macau(y) {
        if (moCache[y]) return moCache[y];
        var map = {};
        function add(d, full, short, t) {
            if (!d || d.getUTCFullYear() !== y) return;
            var k = fmt(d);
            if (map[k]) { map[k].n += ' / ' + full; map[k].s += '/' + short; }
            else map[k] = {n: full, s: short, t: t};
        }
        add(new Date(Date.UTC(y, 0, 1)), '元旦', '元旦', '強制性');
        add(lunar2solar(y, 1, 1), '農曆正月初一', '正月初一', '強制性');
        add(lunar2solar(y, 1, 2), '農曆正月初二', '正月初二', '強制性');
        add(lunar2solar(y, 1, 3), '農曆正月初三', '正月初三', '強制性');
        var qm = getTerm(y, 7);
        if (qm > 0) add(new Date(Date.UTC(y, 3, qm)), '清明節', '清明節', '強制性');
        var es = easterSunday(y);
        add(addDays(es.getTime(), -2), '耶穌受難日', '耶穌受難日', '非強制性');
        add(addDays(es.getTime(), -1), '復活節前日', '復活節前日', '非強制性');
        add(new Date(Date.UTC(y, 4, 1)), '勞動節', '勞動節', '強制性');
        add(lunar2solar(y, 4, 8), '佛誕節', '佛誕節', '非強制性');
        add(lunar2solar(y, 5, 5), '端午節', '端午節', '非強制性');
        add(lunar2solar(y, 8, 16), '中秋節翌日', '中秋翌日', '強制性');
        add(new Date(Date.UTC(y, 9, 1)), '中華人民共和國國慶日', '國慶日', '強制性');
        add(new Date(Date.UTC(y, 9, 2)), '中華人民共和國國慶日翌日', '國慶翌日', '非強制性');
        add(lunar2solar(y, 9, 9), '重陽節', '重陽節', '強制性');
        add(new Date(Date.UTC(y, 10, 2)), '追思節', '追思節', '非強制性');
        add(new Date(Date.UTC(y, 11, 8)), '聖母無原罪瞻禮', '聖母無原罪', '非強制性');
        add(new Date(Date.UTC(y, 11, 20)), '澳門特別行政區成立紀念日', '特區成立紀念', '強制性');
        var dz = getTerm(y, 24);
        if (dz > 0) add(new Date(Date.UTC(y, 11, dz)), '冬至', '冬至', '非強制性');
        add(new Date(Date.UTC(y, 11, 24)), '聖誕節前日', '聖誕前日', '非強制性');
        add(new Date(Date.UTC(y, 11, 25)), '聖誕節', '聖誕節', '非強制性');
        moCache[y] = map;
        return map;
    }

    /* 中國內地官方公布的放假日期區間：[名稱, 起始日, 結束日, 短名] */
    var CN_RANGES = [
        ['元旦', '2025-01-01', '2025-01-01', '元旦'],
        ['春節', '2025-01-28', '2025-02-04', '春節'],
        ['清明節', '2025-04-04', '2025-04-06', '清明節'],
        ['勞動節', '2025-05-01', '2025-05-05', '勞動節'],
        ['端午節', '2025-05-31', '2025-06-02', '端午節'],
        ['國慶節、中秋節', '2025-10-01', '2025-10-08', '國慶中秋'],
        ['元旦', '2026-01-01', '2026-01-03', '元旦'],
        ['春節', '2026-02-15', '2026-02-23', '春節'],
        ['清明節', '2026-04-04', '2026-04-06', '清明節'],
        ['勞動節', '2026-05-01', '2026-05-05', '勞動節'],
        ['端午節', '2026-06-19', '2026-06-21', '端午節'],
        ['中秋節', '2026-09-25', '2026-09-27', '中秋節'],
        ['國慶節', '2026-10-01', '2026-10-07', '國慶節']
    ];
    var cnOfficial = {}, cnOfficialYears = {};
    (function () {
        for (var i = 0; i < CN_RANGES.length; i++) {
            var r = CN_RANGES[i], a = r[1].split('-'), b = r[2].split('-');
            var d = Date.UTC(+a[0], +a[1] - 1, +a[2]), end = Date.UTC(+b[0], +b[1] - 1, +b[2]);
            cnOfficialYears[a[0]] = true;
            for (; d <= end; d += 86400000) cnOfficial[fmt(new Date(d))] = {n: r[0], s: r[3], legal: false};
        }
    })();
    /** 中國內地法定節日當日（沒有官方放假安排的年份使用） */
    function china(y) {
        if (cnCache[y]) return cnCache[y];
        var map = {};
        function add(d, full, short) {
            if (!d || d.getUTCFullYear() !== y) return;
            map[fmt(d)] = {n: full, s: short, legal: true};
        }
        add(new Date(Date.UTC(y, 0, 1)), '元旦', '元旦');
        var cny = lunar2solar(y, 1, 1);
        if (cny) {
            add(addDays(cny.getTime(), -1), '春節（除夕）', '除夕');
            add(cny, '春節', '春節');
            add(addDays(cny.getTime(), 1), '春節', '春節');
            add(addDays(cny.getTime(), 2), '春節', '春節');
        }
        var qm = getTerm(y, 7);
        if (qm > 0) add(new Date(Date.UTC(y, 3, qm)), '清明節', '清明節');
        add(new Date(Date.UTC(y, 4, 1)), '勞動節', '勞動節');
        add(new Date(Date.UTC(y, 4, 2)), '勞動節', '勞動節');
        add(lunar2solar(y, 5, 5), '端午節', '端午節');
        add(lunar2solar(y, 8, 15), '中秋節', '中秋節');
        add(new Date(Date.UTC(y, 9, 1)), '國慶節', '國慶節');
        add(new Date(Date.UTC(y, 9, 2)), '國慶節', '國慶節');
        add(new Date(Date.UTC(y, 9, 3)), '國慶節', '國慶節');
        cnCache[y] = map;
        return map;
    }

    return {
        /** ds 為 YYYY-MM-DD */
        get: function (ds) {
            var y = parseInt(ds, 10), ok = y >= 1901 && y <= 2100;
            return {
                mo: ok ? macau(y)[ds] || null : null,
                cn: cnOfficialYears[y] ? cnOfficial[ds] || null : (ok ? china(y)[ds] || null : null)
            };
        },
        /** 該年份的中國內地假期是否為官方公布的放假安排 */
        cnOfficial: function (y) { return !!cnOfficialYears[y]; }
    };
})();
