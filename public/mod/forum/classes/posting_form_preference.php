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

use stdClass;

/**
 * Which form opens when a user adds a discussion or replies to a post.
 *
 * The quick form is shown in the page with only the subject and message. The advanced form opens on its own page
 * with every posting option. The site default is set by the mod_forum/postingform admin setting, and each user can
 * override it with the forum_postingform user preference.
 *
 * @package    mod_forum
 * @copyright  2026 Roman Huerta Manrique
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class posting_form_preference {
    /** @var string The quick form shown in the page. */
    public const QUICK = 'quick';

    /** @var string The advanced form with all options, on its own page. */
    public const ADVANCED = 'advanced';

    /** @var string Name of the user preference. */
    public const PREFERENCE = 'forum_postingform';

    /**
     * Get the site default posting form.
     *
     * @return string One of the QUICK or ADVANCED constants.
     */
    public static function get_site_default(): string {
        $default = get_config('mod_forum', 'postingform');
        return $default === self::ADVANCED ? self::ADVANCED : self::QUICK;
    }

    /**
     * Get the posting form a user has chosen, falling back to the site default.
     *
     * @param stdClass|int|null $user The user, or null for the current user.
     * @return string One of the QUICK or ADVANCED constants.
     */
    public static function get_for_user(stdClass|int|null $user = null): string {
        $value = get_user_preferences(self::PREFERENCE, null, $user);
        if ($value === self::QUICK || $value === self::ADVANCED) {
            return $value;
        }
        return self::get_site_default();
    }

    /**
     * Whether the advanced form should open instead of the quick in-page form.
     *
     * @param stdClass|int|null $user The user, or null for the current user.
     * @return bool
     */
    public static function use_advanced_form(stdClass|int|null $user = null): bool {
        return self::get_for_user($user) === self::ADVANCED;
    }

    /**
     * Get the options for choosing a posting form, keyed by their value.
     *
     * @return string[]
     */
    public static function get_options(): array {
        return [
            self::QUICK => get_string('postingformquick', 'mod_forum'),
            self::ADVANCED => get_string('postingformadvanced', 'mod_forum'),
        ];
    }
}
