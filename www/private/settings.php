<?php
// Server settings, like the original FQ server's settings dialog.

return [
    // The town's clock: news times, the paper's date, and when midnight rolls the day.
    // Use a PHP timezone name such as 'America/Chicago'. Leave blank to use the host's setting.
    'timezone' => '',

    // The most parlors the town allows. New players see "The town is full" past this.
    'max_players' => 800,

    // The most players logged on at once.
    'max_online' => 500,

    // Tournament mode. 0 keeps the town going forever (No Reset Mode).
    // A number such as 30 runs a tournament that many days long: the top parlor wins,
    // the town is frozen for one day, and then everything is wiped for a new game.
    'tournament_days' => 0,

    // Server news shown under the login box. Color codes like `w work here.
    'server_news' => '',

    // Contact address shown on the login page's "Server Settings" link. Blank shows (none).
    'admin_email' => '',

    // Password for the operator page at /admin.php (player editor, kick, ban, new day, reset).
    // Leave blank to keep the operator page switched off. Use at least 10 characters.
    'admin_password' => '',

    // Server setup, the same switches the original server's Server Setup dialog had.

    // Only one player per IP address per day (the original's IP check). Turn this off if your
    // players share an address, for example a household, a school, or a proxy in front of the site.
    // A player marked "Always allowed" in the operator page skips this check.
    'ip_check' => true,

    // Parlors that have not logged on for this many days close shop and leave town.
    'inactive_days' => 15,

    // At midnight, parlors that never made a sale (and are not online) leave town.
    'delete_unsold' => true,

    // Replace the words listed in private/dist/swear_words.txt in names and chat.
    'swear_filter' => true,

    // Minutes without a click before a player is kicked for inactivity.
    'idle_minutes' => 4,

    // Also save every line of the server log to private/data/log.txt (the operator page always shows the last 80).
    'log_to_disk' => false,

    // Game settings, the same ones the original server's Game Settings dialog had.
    // The values below are the original defaults.

    // Customers a parlor can see each day (a hearse adds its bonus on top).
    'turns_per_day' => 15,

    // Yang's deliveries each player may send per day.
    'yangs_per_day' => 1,

    // Other parlors each player may visit (break into) per day.
    'visits_per_day' => 2,

    // Bury Points won or lost when a player takes a risk.
    'risk' => 50,

    // Daily award for yesterday's top seller and top parlor. Second place gets half, third a quarter.
    'bonus_bp' => 20,
    'bonus_luck' => 25,

    // Bury Point bonus for pricing a funeral within 100 of the customer's limit (+10 within 50).
    'trade_bonus' => 25,

    // Tags a player can receive per day through /give at the cafe. 0 turns tag giving off.
    'tags_per_day' => 1,

    // Gold tags it costs to change your name. 0 turns name changes off.
    'name_change_cost' => 1,

    // What a brand-new parlor starts with.
    'starting_money' => 500,
    'starting_luck' => 50,
    'starting_hp' => 10,
    'starting_strength' => 3,

    // Chance per customer (0.08 = 8%) of a random event, a risk, or a scripted event.
    'normal_odds' => 0.08,
    'luck_odds' => 0.08,
    'script_odds' => 0.05,
];
