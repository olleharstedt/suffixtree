<?php

/*-------------------------------------------------------------------------+
|                                                                          |
| Copyright 2005-2011 The ConQAT Project                                   |
|                                                                          |
| Licensed under the Apache License, Version 2.0 (the "License");          |
| you may not use this file except in compliance with the License.         |
| You may obtain a copy of the License at                                  |
|                                                                          |
|    http://www.apache.org/licenses/LICENSE-2.0                            |
|                                                                          |
| Unless required by applicable law or agreed to in writing, software      |
| distributed under the License is distributed on an "AS IS" BASIS,        |
| WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. |
| See the License for the specific language governing permissions and      |
| limitations under the License.                                           |
+-------------------------------------------------------------------------*/

/**
 * This is a JUnit test for the {@link SuffixTree} class.
 * 
 * @author Benjamin Hummel
 * @author $Author: juergens $
 * 
 * @version $Revision: 34670 $
 * @ConQAT.Rating GREEN Hash: 4E45D05BE72A56B6FDAD0FAD8983A91D
 */
/*
class SuffixTreeTest {

	public function testBasic() {
		$s = "Test basic behaviour of the suffix tree Test basic behaviour of the suffix Creates a list of its characters for a given string tree abcabcabc";
		//@SuppressWarnings({ "unchecked", "rawtypes" })
		$word = (array) $this->stringToList($s);
		$word->add(new SuffixTree.Sentinel());
		$stree = new SuffixTree($word);

		for ($i = 0; $i < $s.length(); ++$i) {
			for ($j = $i + 1; $j <= $s.length(); ++$j) {
				$substr = $s.substring($i, $j);
				assertTrue("Should contain " + $substr, $stree.containsWord($this->stringToList($substr)));
			}
		}

		for (String test : new String[] { "abd", "xyz", $s + "a" }) {
			assertFalse("Should not contain " + test, $stree.containsWord($this->stringToList(test)));
		}
	}

	// Creates a list of its characters for a given string.
	public function stringToList(string $s) {
		List<Character> result = new ArrayList<Character>();
		for (int $i = 0; $i < $s.length(); ++$i) {
			result.add($s.charAt($i));
		}
		return result;
	}

	public function testBigString() {
		String alpha = "abcdefghijklmnopqrstuvwxyz";
		StringBuilder sb = new StringBuilder();
		Random r = new Random(42);
		for (int $i = 0; $i < 500000; ++$i) {
			sb.append(alpha.charAt(r.nextInt(alpha.length())));
		}

		$word = stringToList(sb.toString());

		SuffixTree stree = new SuffixTree($word);

		List<Character> find = stringToList(sb.toString());
		assertTrue(stree.containsWord(find));
		find.add('x');
		assertFalse(stree.containsWord(find));
	}
}
*/

class Character {
    private $c;

    public function __construct(string $c) {
        $this->c = $c;
    }

    public function hashCode(): int {
        return ord($this->c);
    }

    public function equals(object $c): bool {
        return $c == $this->c;
    }

    public function toString(): string {
        return $this->c;
    }
}

/**
 * @return Character[]
 */
function stringToList(string $s) {
    $result = [];
    for ($i = 0; $i < strlen($s); ++$i) {
        $result[] = new Character($s[$i]);
    }
    return $result;
}

require_once 'Sentinel.php';
require_once 'SuffixTree.php';
require_once 'SuffixTreeHashTable.php';

//$s = "Test basic behaviour of the suffix tree Test basic behaviour of the suffix Creates a list of its characters for a given string tree abcabcabc";
$s = "Test";
$word = stringToList($s);
array_push($word, new Sentinel());
$stree = new SuffixTree($word);

for ($i = 0; $i < strlen($s); ++$i) {
    for ($j = $i + 1; $j <= strlen($s); ++$j) {
        $substr = substr($s, $i, $j);
        if ($stree->containsWord(stringToList($substr))) {
            echo 1;
        }
    }
}
