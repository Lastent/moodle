<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_forum;

/**
 * Tests for the posting form preference.
 *
 * @package    mod_forum
 * @category   test
 * @copyright  2026 Roman Huerta Manrique
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(posting_form_preference::class)]
final class posting_form_preference_test extends \advanced_testcase {
    /**
     * Site default values and the posting form they resolve to.
     *
     * @return array
     */
    public static function site_default_provider(): array {
        return [
            'Not configured' => [null, posting_form_preference::QUICK],
            'Quick' => [posting_form_preference::QUICK, posting_form_preference::QUICK],
            'Advanced' => [posting_form_preference::ADVANCED, posting_form_preference::ADVANCED],
            'Unknown value' => ['fancy', posting_form_preference::QUICK],
        ];
    }

    /**
     * The site default falls back to the quick form unless the advanced form is configured.
     *
     * @param string|null $config The value of the admin setting, or null when it is not set.
     * @param string $expected The expected site default.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('site_default_provider')]
    public function test_get_site_default(?string $config, string $expected): void {
        $this->resetAfterTest();
        set_config('postingform', $config, 'mod_forum');

        $this->assertSame($expected, posting_form_preference::get_site_default());
    }

    /**
     * Combinations of site default and user preference, and the posting form the user gets.
     *
     * @return array
     */
    public static function user_preference_provider(): array {
        $quick = posting_form_preference::QUICK;
        $advanced = posting_form_preference::ADVANCED;
        return [
            'No preference, quick site default' => [$quick, null, $quick],
            'No preference, advanced site default' => [$advanced, null, $advanced],
            'Advanced preference overrides the site default' => [$quick, $advanced, $advanced],
            'Quick preference overrides the site default' => [$advanced, $quick, $quick],
            'Unknown preference follows the site default' => [$advanced, 'fancy', $advanced],
        ];
    }

    /**
     * A user's own preference wins over the site default.
     *
     * @param string $sitedefault The site default.
     * @param string|null $preference The user's preference, or null when it is not set.
     * @param string $expected The posting form the user should get.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('user_preference_provider')]
    public function test_get_for_user(string $sitedefault, ?string $preference, string $expected): void {
        $this->resetAfterTest();
        set_config('postingform', $sitedefault, 'mod_forum');
        $user = $this->getDataGenerator()->create_user();
        set_user_preference(posting_form_preference::PREFERENCE, $preference, $user);

        $this->assertSame($expected, posting_form_preference::get_for_user($user));
        $this->assertSame($expected, posting_form_preference::get_for_user($user->id));
        $this->assertSame(
            $expected === posting_form_preference::ADVANCED,
            posting_form_preference::use_advanced_form($user),
        );
    }

    /**
     * Without a user, the current user's preference is used.
     */
    public function test_get_for_current_user(): void {
        $this->resetAfterTest();
        set_config('postingform', posting_form_preference::QUICK, 'mod_forum');
        $user = $this->getDataGenerator()->create_user();
        set_user_preference(posting_form_preference::PREFERENCE, posting_form_preference::ADVANCED, $user);

        $this->setUser($user);
        $this->assertTrue(posting_form_preference::use_advanced_form());

        $this->setGuestUser();
        $this->assertFalse(posting_form_preference::use_advanced_form());
    }

    /**
     * The preference is declared so that invalid values are rejected and null resets it to the site default.
     */
    public function test_preference_definition(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $this->resetAfterTest();
        set_config('postingform', posting_form_preference::ADVANCED, 'mod_forum');
        // Preference definitions are cached statically, and include the site default.
        \core_user::reset_caches();

        $definition = mod_forum_user_preferences()[posting_form_preference::PREFERENCE];
        $this->assertSame(posting_form_preference::ADVANCED, $definition['default']);

        $this->assertSame(
            posting_form_preference::QUICK,
            \core_user::clean_preference(posting_form_preference::QUICK, posting_form_preference::PREFERENCE),
        );
        $this->assertSame(
            posting_form_preference::ADVANCED,
            \core_user::clean_preference('fancy', posting_form_preference::PREFERENCE),
        );
        $this->assertNull(\core_user::clean_preference(null, posting_form_preference::PREFERENCE));
        \core_user::reset_caches();
    }

    /**
     * Every option has a label.
     */
    public function test_get_options(): void {
        $options = posting_form_preference::get_options();

        $this->assertSame([posting_form_preference::QUICK, posting_form_preference::ADVANCED], array_keys($options));
        $this->assertSame(get_string('postingformquick', 'mod_forum'), $options[posting_form_preference::QUICK]);
        $this->assertSame(get_string('postingformadvanced', 'mod_forum'), $options[posting_form_preference::ADVANCED]);
    }
}
