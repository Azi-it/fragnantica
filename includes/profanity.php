<?php
// bad word filter for reviews, using a regular expression.
// it also catches common tricks: capital letters (FUCK), repeated letters (shiiit),
// look-alike characters (sh1t, f@ck, b!tch, a$$) and dots or dashes between letters (f.u.c.k).
// it only matches whole words, so normal words that contain a bad word ("class", "Scunthorpe") are left alone.

// the words to hide; add more here (lowercase letters only)
function bad_words()
{
    return array(
        // English
        'motherfucker', 'fuck', 'shit', 'bullshit', 'bitch', 'asshole', 'ass', 'bastard',
        'dick', 'pussy', 'cunt', 'slut', 'whore', 'damn', 'crap', 'wtf', 'stfu',
        // Filipino
        'putangina', 'tangina', 'puta', 'gago', 'tanga', 'bobo', 'ulol', 'tarantado',
        'punyeta', 'kupal', 'pakyu', 'inutil',
    );
}

// build the pattern once, e.g. "shit" becomes  s+[._-]*h+[._-]*[i1!|*]+[._-]*[t7+]+
function bad_word_pattern()
{
    static $pattern = null;
    if ($pattern !== null) {
        return $pattern;
    }

    // characters people type instead of a letter ("*" can stand for any vowel: f*ck)
    $lookAlikes = array(
        'a' => '[a@4*]', 'e' => '[e3*]', 'i' => '[i1!|*]', 'o' => '[o0*]', 'u' => '[uv*@]',
        's' => '[s5$]', 't' => '[t7+]', 'g' => '[g9]', 'b' => '[b8]',
    );

    $words = array();
    foreach (bad_words() as $word) {
        $letters = array();
        foreach (str_split($word) as $letter) {
            $class = isset($lookAlikes[$letter]) ? $lookAlikes[$letter] : preg_quote($letter, '/');
            $letters[] = $class . '+';          // + = the letter may be repeated: "fuuuck"
        }
        $words[] = implode('[._-]*', $letters); // dots, underscores or dashes may sit between letters
    }

    // (?<![a-z]) and (?![a-z]): no letter right before or after, so only whole words match.
    // the optional ending catches "fucking", "bitches", "shitty", "fucked"
    $pattern = '/(?<![a-z])(?:' . implode('|', $words) . ')(?:ing|in|ed|er|ers|es|s|y)?(?![a-z])/i';
    return $pattern;
}

function has_bad_words($text)
{
    return preg_match(bad_word_pattern(), (string)$text) === 1;
}

// keep the first character of each bad word and replace the rest with *, e.g. "shit" -> "s***"
function mask_bad_words($text)
{
    return preg_replace_callback(bad_word_pattern(), function ($match) {
        $word = $match[0];
        return mb_substr($word, 0, 1) . str_repeat('*', max(1, mb_strlen($word) - 1));
    }, (string)$text);
}
