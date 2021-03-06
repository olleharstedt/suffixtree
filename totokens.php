<?php

$file = $argv[1];
$json = [];

if (is_file($file)) {
    $content = file_get_contents($file);
    $tokens = token_get_all($content);

    // Copied from phpcpd
    $tokensIgnoreList = [
        T_INLINE_HTML        => true,
        T_COMMENT            => true,
        T_DOC_COMMENT        => true,
        T_OPEN_TAG           => true,
        T_OPEN_TAG_WITH_ECHO => true,
        T_CLOSE_TAG          => true,
        T_WHITESPACE         => true,
        T_USE                => true,
        T_NS_SEPARATOR       => true,
    ];
    foreach($tokens as $token) {
        if (is_array($token)) {
            if (isset($tokensIgnoreList[$token[0]])) {
                continue;
            }
            $json[] = [
                'token_code' => $token[0],
                'token_name' => token_name($token[0]),
                'line' => $token[2],
                'content' => $token[1],
                'file' => $file
            ];
        } 
    }
} else {
    $rdi = new RecursiveDirectoryIterator($file);
    foreach (new RecursiveIteratorIterator($rdi) as $file => $info) {
        if ($file === '.' || $file === '..') {
        } else {
            $parts = pathinfo($file);
            if (isset($parts['extension']) && $parts['extension'] === 'php') {
                if (strpos($file, 'tests')) {
                    continue;
                }
                $content = file_get_contents($file);
                $tokens = token_get_all($content);

                // Copied from phpcpd
                $tokensIgnoreList = [
                    T_INLINE_HTML        => true,
                    T_COMMENT            => true,
                    T_DOC_COMMENT        => true,
                    T_OPEN_TAG           => true,
                    T_OPEN_TAG_WITH_ECHO => true,
                    T_CLOSE_TAG          => true,
                    T_WHITESPACE         => true,
                    T_USE                => true,
                    T_NS_SEPARATOR       => true,
                ];
                foreach($tokens as $token) {
                    if (is_array($token)) {
                        if (isset($tokensIgnoreList[$token[0]])) {
                            continue;
                        }
                        $json[] = [
                            'token_code' => $token[0],
                            'token_name' => token_name($token[0]),
                            'line' => $token[2],
                            'content' => $token[1],
                            'file' => $file
                        ];
                    } 
                }
            }
        }
    }
}

echo json_encode($json);

//echo "\n";
//echo "\n";
//$str = "5555555555,5556565656[565757575757,5758585858[585959595959595959]60],61],6263(63,6363),6364(64,6464,646464646464),6465(65,6565),6566(66,6666,666666666666),6667(67,6767,676767676767),6768(68,6868,686868686868),6869(69,6969,696969696969),6970(70,7070,707070707070),7071(71,7171),71727373(73,7373,737373737373),73);74}7577798080808080()80{81828282();82}8385878888888888()88{89909090(909191919191,919292929292,929393939393,939494949494,949595959595,959696969696,969797979797,979898989898,989999999999,99100100100100100,100101101101101101,101102102102102102,102103103103103103,103104104104104104,104105105105105105,105106106106106106,106107107107107107,107108108108108108,108109109109109109,109110110110110110,110111111111111111,111112112112112112,112113113113113113,113);114}115117128129129129129129()129{130131131=131131()131131131131(131,131131()131131[131]);131133133=133133133133();133134134134(134,134134134134);13";
//$str = str_replace([",", "[", "]", "(", ")", "{", "}", ";", "="], "", $str);
//$tokens = str_split($str, 3);
//foreach ($tokens as $token) {
//echo token_name($token);
//}
