<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');

class UtilTest extends TestCase {
    public function testUtil(): void {
        $this->assertEquals(2,    article(2),       'article-2-a');
        $this->assertEquals(3,    article(3, 'an'), 'article-3-an');
        $this->assertEquals('a',  article(1),       'article-1-a');
        $this->assertEquals('an', article(1, 'an'), 'article-1-an');

        $this->assertEquals('',        display_str([]),       'display-str-array');
        $this->assertEquals('',        display_str(null),     'display-str-null');
        $this->assertEquals('',        display_str(false),    'display-str-false');
        $this->assertEquals(42,        display_str(42),       'display-str-42');
        $this->assertEquals('a&lt;b',  display_str('a<b'),    'display-str-a-lt-b');
        $this->assertEquals('&#8364;', display_str('&#128;'), 'display-str-entity-128');
        $this->assertEquals('&#376;',  display_str('&#159;'), 'display-str-entity-159');

        $this->assertEquals('a<b', reverse_display_str('a<b'),     'display-revstr-a-lt-b');
        $this->assertEquals(' ',   reverse_display_str('&#8364;'), 'display-revstr-entity-128');
        $this->assertEquals('Ÿ',   reverse_display_str('&#376;'),  'display-revstr-entity-159');

        $this->assertTrue(is_number(1), 'is-number-1');
        $this->assertFalse(is_number(3.14), 'is-number-3.14');
        $this->assertFalse(is_number('abc'), 'is-number-abc');

        $this->assertEquals('s',  plural(2),       'plural-2-s');
        $this->assertEquals('es', plural(3, 'es'), 'plural-3-es');
        $this->assertEquals('',   plural(1),       'plural-1-s');
        $this->assertEquals('',   plural(1, 'es'), 'plural-1-es');

        $browser = parse_user_agent("Lidarr/1.2.4 (windows 95)");
        $this->assertEquals('Lidarr', $browser['Browser'], 'ua-lidarr-name');
        $this->assertEquals('1.2', $browser['BrowserVersion'], 'ua-lidarr-version');
        $this->assertEquals('Windows', $browser['OperatingSystem'], 'ua-lidarr-os-name');
        $this->assertEquals('95', $browser['OperatingSystemVersion'], 'ua-lidarr-os-version');

        $browser = parse_user_agent("VarroaMusica/1234dev");
        $this->assertEquals('VarroaMusica', $browser['Browser'], 'ua-varroa-name');
        $this->assertEquals('1234', $browser['BrowserVersion'], 'ua-varroa-version');
        $this->assertNull($browser['OperatingSystem'], 'ua-varroa-os-name');
        $this->assertNull($browser['OperatingSystemVersion'], 'ua-varroa-os-version');

        $this->assertEquals('?,?,?', placeholders(['a', 'b', 'c']), 'placeholders-3');
        $this->assertEquals('(?),(?)', placeholders(['d', 'e'], '(?)'), 'placeholders-custom');

        $this->assertEquals(32, strlen(randomString()), 'random-string');

        $this->assertEquals('--------.txt', safeFilename('"-*-/-:-<->-?-\\-|.txt'), 'safe-filename');

        $this->assertEquals('abc def ghi', shortenString('abc def ghi', 20), 'shorten-string-unchanged');
        $this->assertEquals('abc def…', shortenString('abc def ghi', 10), 'shorten-string-10-ellipsis');
        $this->assertEquals('abc def', shortenString('abc def ghi', 10, false, false), 'shorten-string-10-no-ellipsis');
        $this->assertEquals('abcdefghij…', shortenString('abcdefghijklm', 10, true), 'shorten-string-13-shorten-ellipsis');
        $this->assertEquals('abcdefghij', shortenString('abcdefghijklm', 10, true, false), 'shorten-string-13-shorten-no-ellipsis');
        $this->assertEquals('abcdefghij…', shortenString('abcdefghijklm', 10, false), 'shorten-string-13-shorten-ellipsis');
    }

    public function testFormat(): void {
        $this->assertFalse(ratio(0, 0),                  'format-ratio-0-0-x');
        $this->assertFalse(ratio(0, 0, 4),               'format-ratio-0-0-4');
        $this->assertEquals('∞',       ratio(1, 0),      'format-ratio-1-0-4');
        $this->assertEquals('∞',       ratio(1, 0, 4),   'format-ratio-1-0-x');
        $this->assertEquals('0.50',    ratio(2, 4),      'format-ratio-2-4-x');
        $this->assertEquals('0.5000',  ratio(2, 4, 4),   'format-ratio-2-4-4');
        $this->assertEquals('0.00',    ratio(0, 4),      'format-ratio-0-4-x');
        $this->assertEquals('0.0000',  ratio(0, 4, 4),   'format-ratio-0-4-4');
        $this->assertEquals('1.00',    ratio(4, 4),      'format-ratio-4-4-x');
        $this->assertEquals('1.000',   ratio(4, 4, 3),   'format-ratio-4-4-3');
        $this->assertEquals('10.00',   ratio(40, 4),     'format-ratio-40-4-x');
        $this->assertEquals('10.000',  ratio(40, 4, 3),  'format-ratio-40-4-3');
        $this->assertEquals('0.00',    ratio(-2, 4),     'format-ratio--2-4-x');

        $this->assertEquals('0.4999',  ratio(20000, 40001, 4), 'format-ratio-20k-hi');
        $this->assertEquals('0.5000',  ratio(20000, 39999, 4), 'format-ratio-20k-lo-1');
        $this->assertEquals('0.5001',  ratio(20000, 39990, 4), 'format-ratio-20k-lo-2');

        $this->assertEquals('r00', ratio_css(0.0999), 'format-get-ratio-css-r00');
        $this->assertEquals('r01', ratio_css(0.1000), 'format-get-ratio-css-r01-lo');
        $this->assertEquals('r01', ratio_css(0.1999), 'format-get-ratio-css-r01-hi');
        $this->assertEquals('r02', ratio_css(0.2000), 'format-get-ratio-css-r02-lo');
        $this->assertEquals('r02', ratio_css(0.2999), 'format-get-ratio-css-r02-hi');
        $this->assertEquals('r03', ratio_css(0.3000), 'format-get-ratio-css-r03-lo');
        $this->assertEquals('r03', ratio_css(0.3999), 'format-get-ratio-css-r03-hi');
        $this->assertEquals('r04', ratio_css(0.4000), 'format-get-ratio-css-r04-lo');
        $this->assertEquals('r04', ratio_css(0.4999), 'format-get-ratio-css-r04-hi');
        $this->assertEquals('r05', ratio_css(0.5000), 'format-get-ratio-css-r05-lo');
        $this->assertEquals('r05', ratio_css(0.5999), 'format-get-ratio-css-r05-hi');
        $this->assertEquals('r06', ratio_css(0.6000), 'format-get-ratio-css-r06-lo');
        $this->assertEquals('r06', ratio_css(0.6999), 'format-get-ratio-css-r06-hi');
        $this->assertEquals('r07', ratio_css(0.7000), 'format-get-ratio-css-r07-lo');
        $this->assertEquals('r07', ratio_css(0.7999), 'format-get-ratio-css-r07-hi');
        $this->assertEquals('r08', ratio_css(0.8000), 'format-get-ratio-css-r08-lo');
        $this->assertEquals('r08', ratio_css(0.8999), 'format-get-ratio-css-r08-hi');
        $this->assertEquals('r09', ratio_css(0.9000), 'format-get-ratio-css-r09-lo');
        $this->assertEquals('r09', ratio_css(0.9999), 'format-get-ratio-css-r09-hi');
        $this->assertEquals('r10', ratio_css(1.0000), 'format-get-ratio-css-r10-hi');
        $this->assertEquals('r10', ratio_css(1.9999), 'format-get-ratio-css-r10-hi');
        $this->assertEquals('r20', ratio_css(2.0000), 'format-get-ratio-css-r20-hi');
        $this->assertEquals('r20', ratio_css(4.9999), 'format-get-ratio-css-r20-hi');
        $this->assertEquals('r50', ratio_css(5.0000), 'format-get-ratio-css-r50-hi');

        $this->assertEquals('--', ratio_html(0, 0), 'format-html-0-0-t');
        $this->assertEquals('--', ratio_html(0, 0, false), 'format-html-0-0-f');
        $this->assertEquals('<span class="tooltip r99" title="Infinite">∞</span>', ratio_html(100, 0), 'format-html-100-0-t');
        $this->assertEquals('∞', ratio_html(100, 0, false), 'format-html-0-0-f');
        $this->assertEquals('<span class="tooltip r07" title="0.70000">0.70</span>', ratio_html(70, 100), 'format-html-70-100-t');
        $this->assertEquals('0.70', ratio_html(70, 100, false), 'format-html-70-100-f');

        $this->assertEquals(0,  get_bytes('5.05a'), 'format-bytes-0');
        $this->assertEquals(1_024,  get_bytes('1.00k'), 'format-bytes-1-00k');
        $this->assertEquals(1_044,  get_bytes('1.02k'), 'format-bytes-1-02k');
        $this->assertEquals(2_097_152,  get_bytes('2.00m'), 'format-bytes-2-00m');
        $this->assertEquals(2_139_095,  get_bytes('2.04m'), 'format-bytes-2-04m');
        $this->assertEquals(4_294_967_296,  get_bytes('4.00g'), 'format-bytes-4-00g');
        $this->assertEquals(4_337_916_969,  get_bytes('4.04g'), 'format-bytes-4-04g');
        $this->assertEquals(5_497_558_138_880,  get_bytes('5.00t'), 'format-bytes-5-00t');
        $this->assertEquals(5_552_533_720_269,  get_bytes('5.05t'), 'format-bytes-5-05t');

        $this->assertEquals('1,023 B',   byte_format(1023), 'format-size-1023');
        $this->assertEquals('1.00 KiB',  byte_format(1024), 'format-size-1K');
        $this->assertEquals('10.00 KiB', byte_format(1024 * 10), 'format-size-10K');
        $this->assertEquals('1.00 MiB',  byte_format(1024 ** 2), 'format-size-1M');
        $this->assertEquals('2.00 GiB',  byte_format(2 * 1024 ** 3), 'format-size-2G');
        $this->assertEquals('4.200 PiB', byte_format(4.2 * 1024 ** 5), 'format-size-4.2P');
        $this->assertEquals('8.500 EiB', byte_format(8.5 * 1024 ** 6), 'format-size-8.5E');

        $this->assertEquals('1.02k',  human_format(1023), 'format-human-1023');
        $this->assertEquals('1.03k',  human_format(1025), 'format-human-1K');
        $this->assertEquals('10.24k', human_format(1024 * 10), 'format-human-10K');
        $this->assertEquals('1.05M',  human_format(1024 ** 2), 'format-human-1M');
        $this->assertEquals('2.15G',  human_format(2 * 1024 ** 3), 'format-human-2G');
        $this->assertEquals('4.73P', human_format(4.2 * 1024 ** 5), 'format-human-4.2P');
        $this->assertEquals('9.8E + 18', human_format(8.5 * 1024 ** 6), 'format-human-8.5E');
    }
}
