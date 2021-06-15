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
 * An extension of the suffix tree adding an algorithm for finding approximate
 * clones, i.e. substrings which are similar.
 * 
 * @author $Author: hummelb $
 * @version $Revision: 43151 $
 * @ConQAT.Rating GREEN Hash: BB94CD690760BC239F04D32D5BCAC33E
 *
 * JARs needed:
 *   https://mvnrepository.com/artifact/org.json/json/20140107
 *
 * Compile with
 *   javac -cp .:json-20140107.jar ApproximateCloneDetectingSuffixTree.java
 *
 * Run with
 *   java -cp .:json-20140107.jar ApproximateCloneDetectingSuffixTree
 *
 * (-cp = class path)
 */
class ApproximateCloneDetectingSuffixTree extends SuffixTree
{
    /**
     * The number of leaves reachable from the given node (1 for leaves).
     * @var int[]
     * */
	private $leafCount;

    /**
     * This is the distance between two entries in the {@link #cloneInfos} map.
     * @var int
     */
	private $INDEX_SPREAD = 10;

    /**
     * This map stores for each position the relevant clone infos.
     * @var array<int, CloneInfo>
     */
	//private final ListMap<Integer, CloneInfo> cloneInfos = new ListMap<Integer, CloneInfo>();
	private $cloneInfos = [];

	/**
	 * The maximal length of a clone. This influences the size of the
	 * (quadratic) {@link #edBuffer}.
     * @var int
	 */
	private $MAX_LENGTH = 1024;

    /**
     * Buffer used for calculating edit distance.
     * @var array<int[]>
     */
	private $edBuffer = [];

    /**
     * The minimal length of clones to return.
     * @var int
     */
	protected $minLength;

    /**
     * Number of units that must be equal at the start of a clone
     * @var int
     */
	private $headEquality;

	/**
	 * Create a new suffix tree from a given word. The word given as parameter
	 * is used internally and should not be modified anymore, so copy it before
	 * if required.
	 * <p>
	 * This only word correctly if the given word is closed using a sentinel
	 * character.
     *
     * @param array $word List of tokens to analyze
	 */
    public function __construct(array $word)
    {
        $arr = array_fill(0, $this->MAX_LENGTH, 0);
        $this->edBuffer = array_fill(0, $this->MAX_LENGTH, $arr);

        parent::__construct($word);
		$this->ensureChildLists();
		$this->leafCount = array_fill(0, $this->numNodes, 0);
		$this->initLeafCount(0);
	}

	/**
	 * Initializes the {@link #leafCount} array which given for each node the
	 * number of leaves reachable from it (where leaves obtain a value of 1).
     *
     * @param int $node
     * @return void
	 */
    private function initLeafCount(int $node)
    {
		$this->leafCount[$node] = 0;
		for ($e = $this->nodeChildFirst[$node]; $e >= 0; $e = $this->nodeChildNext[$e]) {
			$this->initLeafCount($this->nodeChildNode[$e]);
			$this->leafCount[$node] += $this->leafCount[$this->nodeChildNode[$e]];
		}
		if ($this->leafCount[$node] == 0) {
			$this->leafCount[$node] = 1;
		}
	}

    /**
     * TODO: Add options:
     *   --min-tokens
     *   --min-lines
     *   --edit-distance
     * @todo move out
     */
    /*
    public static void main(String[] args) throws ConQATException, IOException {
        //String input = Files.readString(Paths.get("QuestionTheme.php"), StandardCharsets.US_ASCII);
        //input.replaceAll("\\s+","");
        //String input = "bla bla bla test bla bla bla bla bla mooooo something else";
        //List<Character> word = SuffixTreeTest.stringToList(input);

        List<PhpToken> word = new ArrayList<PhpToken>();

        String filename = "tokens.json";
        String content = new Scanner(new File(filename)).useDelimiter("\\Z").next();
        JSONArray json = new JSONArray(content);
        for(int n = 0; n < json.length(); n++)
        {
            JSONObject object = (JSONObject) json.get(n);
            PhpToken t = new PhpToken(
                (int) object.get("token_code"),
                (String) object.get("token_name"),
                (int) object.get("line"),
                (String) object.get("file"),
                (String) object.get("content")
            );
            word.add(t);
        }
        word.add(new Sentinel(0, "_", 0, "_", "_"));

        //System.out.println("Word size = " + word.size());

		ApproximateCloneDetectingSuffixTree stree = new ApproximateCloneDetectingSuffixTree(
                word) {
            @Override
            protected boolean mayNotMatch(Object character) {
                return character instanceof Sentinel;
            }

            @Override
            protected void reportBufferShortage(int leafStart, int leafLength) {
                System.out.println("Encountered buffer shortage: " + leafStart
                        + " " + leafLength);
            }
        };
        //List<List<String>> cloneClasses = stree.findClones(1, 1, 3);

        stree.findClones(10, 10, 10);
    }
     */

	/**
	 * Finds all clones in the string (List) used in the constructor.
	 * 
	 * @param int $minLength the minimal length of a clone in tokens (not lines)
	 * @param int $maxErrors the maximal number of errors/gaps allowed
	 * @param int $headEquality the number of elements which have to be the same at the beginning of a clone
     * @return void
     * @throws ConQATException
	 */
    public function findClones(int $minLength, int $maxErrors, int $headEquality)
    {
		$this->minLength = $minLength;
		$this->headEquality = $headEquality;
		//$this->cloneInfos->clear();

		for ($i = 0; $i < count($this->word); ++$i) {
			// Do quick start, as first character has to match anyway.
			$node = $this->nextNode->get(0, $this->word[$i]);
			if ($node < 0 || $this->leafCount[$node] <= 1) {
				continue;
			}

			// we know that we have an exact match of at least 'length'
			// characters, as the word itself is part of the suffix tree.
			$length = $this->nodeWordEnd[$node] - $this->nodeWordBegin[$node];
			$numReported = 0;
			for ($e = $this->nodeChildFirst[$node]; $e >= 0; $e = $this->nodeChildNext[$e]) {
				if ($this->matchWord($i, $i + $length, $this->nodeChildNode[$e], $length,
						$maxErrors)) {
					++$numReported;
				}
			}
			if ($length >= $this->minLength && $numReported != 1) {
				$this->reportClone($i, $i + $length, $node, $length, $length);
			}
		}

        /** @var int[] */
        //$lengths = new ArrayList<Integer>();
        //Map<Integer, CloneInfo> map = new HashMap<>();
        /** @var array<int, CloneInfo> */
        $map = [];
        /*
        Comparator<CloneInfo> comp = new Comparator<CloneInfo>() {
            @Override
            public int compare(CloneInfo c, CloneInfo d) {
                //return d.token.line - c.token.line;
                return d.length - c.length;
            }
        };
         */
        //TreeSet<CloneInfo> tree = new TreeSet<CloneInfo>(comp);

        //List<CloneInfo> allClones = new ArrayList<CloneInfo>();
		for ($index = 0; $index <= count($this->word); ++$index) {
            /** @var CloneInfo[] */
			//$existingClones = $this->cloneInfos.getCollection($index);
			$existingClones = $this->cloneInfos[$index] ?? null;
			if ($existingClones != null) {
                foreach ($existingClones as $ci) {
                    // length = number of tokens
                    // TODO: min token length
                    if ($ci->length > 50) {
                        //allClones.add($ci);
                        //$lengths.add($ci.length);
                        //tree.add($ci);
                        /** @var CloneInfo */
                        $previousCi = $map[$ci->token->line];
                        if ($previousCi == null) {
                            $map[$ci->token->line] =  $ci;
                        } else if ($ci->length > $previousCi->length) {
                            $map[$ci->token->line] = $ci;
                        }
                        //System.out.println("length = " + $ci.length + ", occurrences = " + $ci.occurrences);
                        //System.out.println("line = " + $ci.token.line);
                        /** @var int[] */
                        $others = $ci->otherClones->extractFirstList();
                        for ($j = 0; $j < count($others); $j++) {
                            $otherStart = $others[$j];
                            /** @var PhpToken */
                            $t = $this->word[$otherStart];
                            //System.out.println("\tother clone start = " + t.line);
                        }
                    }
				}
			}
		}

        //if (allClones == null) {
        //} else {
            //allClones.sort((CloneInfo a, CloneInfo b) -> a.length - b.length);
            //allClones.sort((CloneInfo a, CloneInfo b) -> a.token.line - b.token.line);
        //}

        //Iterator<CloneInfo> itr2 = allClones.iterator();
        //while (itr2.hasNext()) {
            //CloneInfo $ci = itr2.next();
            //System.out.println($ci.token.line);
        //}

        //Iterator<CloneInfo> itr2 = tree.iterator();
        //while (itr2.hasNext()) {
            //CloneInfo $ci = itr2.next();
            //System.out.println($ci.length);
        //}

        /** @var CloneInfo[] */
        $values = array_values($map);
        //Collections.sort($values, (a, b) -> b.length - a.length);
        usort($values, function ($a, $b) { return $b->length - $a->length;});
        //Set set = $map.entrySet();
        //Iterator itr = $values.iterator();
        printf(
            "\nFound %d clones with %d duplicated lines in %d files:\n\n",
            count($values),
            0,  // TODO: Fix
            0
        );
        // TODO: Filter overlapping clones.
        /*
        while(itr.hasNext()) {
        //for (int i = 0; i < keys.size(); i++) {
            //Map.Entry entry = (Map.Entry) itr.next();  
            //CloneInfo $ci = (CloneInfo) entry.getValue();
            //CloneInfo $ci = (CloneInfo) $map.get(keys.get(i));
            CloneInfo $ci = (CloneInfo) itr.next();
            try {
                PhpToken lastToken = (PhpToken) $this->word.get($ci.position + $ci.length);
                int lines = lastToken.line - $ci.token.line;
                System.out.printf(
                    "  - %s:%d-%d (%d lines)\n",
                    $ci.token.file,
                    $ci.token.line,
                    $ci.token.line + lines - 1,
                    lines
                );
            } catch(IndexOutOfBoundsException $e) {
                System.out.printf("index out of bounds, $ci.position = %d, $ci.length = %d", $ci.position, $ci.length);
            }
            List<Integer> others = $ci.otherClones.extractFirstList();
            for (int j = 0; j < others.size(); j++) {
                int otherStart = others.get(j);
                PhpToken t = (PhpToken) $this->word.get(otherStart);
                PhpToken lastToken = (PhpToken) $this->word.get($ci.position + $ci.length);
                int lines = lastToken.line - $ci.token.line;
                System.out.printf(
                        "    %s:%d-%d\n",
                        t.file,
                        t.line,
                        t.line + lines - 1
                );
            }
            System.out.println("");
        }
         */
	}

	/**
	 * Performs the approximative matching between the input word and the tree.
	 * 
	 * @param int $wordStart the start position of the currently matched word (position in
	 *            the input word).
	 * @param int $wordPosition the current position along the input word.
	 * @param int $node the node we are currently at (i.e. the edge leading to this
	 *            node is relevant to us).
	 * @param int $nodeWordLength the length of the word found along the nodes (this may be
	 *            different from the length along the input word due to gaps).
	 * @param int $maxErrors the number of errors still allowed.
	 * @return boolean whether some clone was reported
     * @throws ConQATException
	 */
    private function matchWord(int $wordStart, int $wordPosition, int $node, int $nodeWordLength, int $maxErrors)
    {
		// We are aware that this method is longer than desirable for code
		// reading. However, we currently do not see a refactoring that has a
		// sensible cost-benefit ratio. Suggestions are welcome!

		// self match?
		if ($this->leafCount[$node] == 1 && $this->nodeWordBegin[$node] == $wordPosition) {
			return false;
		}

		$currentNodeWordLength = min($this->nodeWordEnd[$node] - $this->nodeWordBegin[$node], $this->MAX_LENGTH - 1);

		// do min edit distance
        /** @var int */
		$currentLength = $this->calculateMaxLength($wordStart, $wordPosition, $node,
				$maxErrors, $currentNodeWordLength);

		if ($currentLength == 0) {
			return false;
		}

		if ($currentLength >= $this->MAX_LENGTH - 1) {
			$this->reportBufferShortage($this->nodeWordBegin[$node], $currentNodeWordLength);
		}

		// calculate cheapest match
		$best = $maxErrors + 42;
		$iBest = 0;
		$jBest = 0;
		for ($k = 0; $k <= $currentLength; ++$k) {
			$i = $currentLength - $k;
			$j = $currentLength;
			if ($this->edBuffer[$i][$j] < $best) {
				$best = $this->edBuffer[$i][$j];
				$iBest = $i;
				$jBest = $j;
			}

			$i = $currentLength;
			$j = $currentLength - $k;
			if ($this->edBuffer[$i][$j] < $best) {
				$best = $this->edBuffer[$i][$j];
				$iBest = $i;
				$jBest = $j;
			}
		}

		while ($wordPosition + $iBest < count($this->word)
				&& $jBest < $currentNodeWordLength
				&& $this->word.get($wordPosition + $iBest) != $this->word
						.get($this->nodeWordBegin[$node] + $jBest)
				&& $this->word.get($wordPosition + $iBest).equals(
						$this->word.get($this->nodeWordBegin[$node] + $jBest))) {
			++$iBest;
			++$jBest;
		}

		$numReported = 0;
		if ($currentLength == $currentNodeWordLength) {
			// we may proceed
			for ($e = $this->nodeChildFirst[$node]; $e >= 0; $e = $this->nodeChildNext[$e]) {
				if ($this->matchWord($wordStart, $wordPosition + $iBest,
						$this->nodeChildNode[$e], $nodeWordLength + $jBest, $maxErrors
								- $best)) {
					++$numReported;
				}
			}
		}

		// do not report locally if had reports in exactly one subtree (would be
		// pure subclone)
		if ($numReported == 1) {
			return true;
		}

		// disallow tail changes
		while ($iBest > 0
				&& $jBest > 0
				&& !$this->word[$wordPosition + $iBest - 1]->equals(
						$this->word[$this->nodeWordBegin[$node] + $jBest - 1])) {

			if ($iBest > 1
					&& $this->word[$wordPosition + $iBest - 2]->equals(
							$this->word[$this->nodeWordBegin[$node] + $jBest - 1])) {
				--$iBest;
			} else if ($jBest > 1
					&& $this->word[$wordPosition + $iBest - 1]->equals(
							$this->word[$this->nodeWordBegin[$node] + $jBest - 2])) {
				--$jBest;
			} else {
				--$iBest;
				--$jBest;
			}
		}

		// report if real clone
		if ($iBest > 0 && $jBest > 0) {
			$numReported += 1;
			$this->reportClone($wordStart, $wordPosition + $iBest, $node, $jBest,
					$nodeWordLength + $jBest);
		}

		return $numReported > 0;
	}

	/**
	 * Calculates the maximum length we may take along the word to the current
	 * $node (respecting the number of errors to make). *
	 * 
	 * @param int $wordStart the start position of the currently matched word (position in
	 *            the input word).
	 * @param int $wordPosition the current position along the input word.
	 * @param int $node the node we are currently at (i.e. the edge leading to this
	 *            node is relevant to us).
	 * @param int $maxErrors the number of errors still allowed.
	 * @param int $currentNodeWordLength the length of the word found along the nodes (this may be
	 *            different from the actual length due to buffer limits).
	 * @return int the maximal length that can be taken.
	 */
    private function calculateMaxLength(
        int $wordStart,
        int $wordPosition,
        int $node,
        int $maxErrors,
        int $currentNodeWordLength)
    {
		$this->edBuffer[0][0] = 0;
		$currentLength = 1;
		for (; $currentLength <= $currentNodeWordLength; ++$currentLength) {
            /** @var int */
			$best = $currentLength;
			$this->edBuffer[0][$currentLength] = $currentLength;
			$this->edBuffer[$currentLength][0] = $currentLength;

			if ($wordPosition + $currentLength >= count($this->word)) {
				break;
			}

			// deal with case that character may not be matched (sentinel!)
			$iChar = $this->word[$wordPosition + $currentLength - 1];
			$jChar = $this->word[$this->nodeWordBegin[$node] + $currentLength - 1];
			if ($this->mayNotMatch($iChar) || $this->mayNotMatch($jChar)) {
				break;
			}

			// usual matrix completion for edit distance
			for ($k = 1; $k < $currentLength; ++$k) {
				$best = Math.min(
						$best,
						fillEDBuffer($k, $currentLength, $wordPosition,
								$this->nodeWordBegin[$node]));
			}
			for ($k = 1; $k < $currentLength; ++$k) {
				$best = min(
						$best,
						$this->fillEDBuffer($currentLength, $k, $wordPosition,
								$this->nodeWordBegin[$node]));
			}
			$best = min(
					$best,
					$this->fillEDBuffer($currentLength, $currentLength, $wordPosition,
							$this->nodeWordBegin[$node]));

			if ($best > $maxErrors
					|| $wordPosition - $wordStart + $currentLength <= $this->headEquality
					&& $best > 0) {
				break;
			}
		}
		--$currentLength;
		return $currentLength;
	}

    /**
     * @return void
     * @throws ConQATException
     */
	private function reportClone(int $wordBegin, int $wordEnd, int $currentNode,
        int $nodeWordPos, int $nodeWordLength)
    {
        /** @var int */
		$length = $wordEnd - $wordBegin;
		if ($length < $this->minLength || $nodeWordLength < $this->minLength) {
			return;
		}

		//PairList<Integer, Integer> otherClones = new PairList<Integer, Integer>();
        /** @var array<array{int, int}> */
		$otherClones = [];
        $this->findRemainingClones(
            $otherClones,
            $nodeWordLength,
            $currentNode,
            $this->nodeWordEnd[$currentNode] - $this->nodeWordBegin[$currentNode] - $nodeWordPos,
            $wordBegin
        );

		$occurrences = 1 + count($otherClones);

		// check whether we may start from here
        /** @var PhpToken */
        $t = $this->word[$wordBegin];
        /** @var CloneInfo */
		$newInfo = new CloneInfo($length, $wordBegin, $occurrences, $t, $otherClones);
		for ($index = max(0, $wordBegin - $this->INDEX_SPREAD + 1); $index <= $wordBegin; ++$index) {
            /** @var CloneInfo */
			$existingClones = $this->cloneInfos.getCollection($index);
			if ($existingClones != null) {
				//for (CloneInfo cloneInfo : $existingClones) {
                foreach ($existingClones as $cloneInfo) {
					if ($cloneInfo->dominates($newInfo, $wordBegin - $index)) {
						// we already have a dominating clone, so ignore
						return;
					}
				}
			}
		}

		// report clone
		//consumer.startCloneClass($length);
		//consumer.addClone($wordBegin, $length);
		//for (int i = $wordBegin; i < $length; i++) {
			//System.out.print($this->word.get(i) + " ");
		//}
        //PhpToken t = (PhpToken) $this->word.get($wordBegin);
        //System.out.println("line = " + t.line + ", $length = " + $length);

		for ($clone = 0; $clone < count($otherClones); ++$clone) {
			$start = $otherClones.getFirst($clone);
			$otherLength = $otherClones.getSecond($clone);
			//consumer.addClone($start, $otherLength);
		}

		// is this clone actually relevant?
		//if (!consumer.completeCloneClass()) {
			//return;
		//}

		// add clone to $otherClones to avoid getting more duplicates
		for ($i = $wordBegin; $i < $wordEnd; $i += $this->INDEX_SPREAD) {
			$this->cloneInfos[$i][] = new CloneInfo($length - ($i - $wordBegin), $wordBegin, $occurrences, $t, $otherClones);
		}
        //PhpToken t = (PhpToken) $this->word.get($wordBegin);
        //System.out.print("line = " + t.line + ", $length = " + $length + "; ");
		for ($clone = 0; $clone < count($otherClones); ++$clone) {
			$start = $otherClones->getFirst($clone);
			$otherLength = $otherClones->getSecond($clone);
            //PhpToken s = (PhpToken) $this->word.get($start);
            //System.out.print("$start t.line = " + s.line + ", $otherlength =  " + $otherLength + " ");
            for ($j = 0; $j < $otherLength; $j++) {
                /** @var PhpToken */
                $r = $this->word[$j + $start];
                //System.out.print($r.content + " " );
            }
			for ($i = 0; $i < $otherLength; $i += $this->INDEX_SPREAD) {
				//$this->cloneInfos.add($start + $i, new CloneInfo($otherLength - $i, $wordBegin, occurrences, t, $otherClones));
				$this->cloneInfos[$start + $i][] = new CloneInfo($otherLength - $i, $wordBegin, occurrences, t, $otherClones);
			}
		}
        //System.out.println("");
	}


	/**
	 * Fills the edit distance buffer at position (i,j).
	 * 
	 * @param int $i the first index of the buffer.
	 * @param int $j the second index of the buffer.
	 * @param int $iOffset the offset where the word described by $i starts.
	 * @param int $jOffset the offset where the word described by $j starts.
	 * @return int the value inserted into the buffer.
	 */
    private function fillEDBuffer(int $i, int $j, int $iOffset, int $jOffset)
    {
        /** @var JavaObjectInterface */
		$iChar = $this->word[$iOffset + $i - 1];
        /** @var JavaObjectInterface */
		$jChar = $this->word[$jOffset + $j - 1];

		$insertDelete = 1 + min($this->edBuffer[$i - 1][$j], $this->edBuffer[$i][$j - 1]);
		$change = $this->edBuffer[$i - 1][$j - 1] + ($iChar->equals($jChar) ? 0 : 1);
		return $this->edBuffer[$i][$j] = min($insertDelete, $change);
	}

	/**
	 * Fills a list of pairs giving the start positions and lengths of the
	 * remaining clones.
	 * 
	 * @param array<array{int, int}> $clonePositions the clone positions being filled (start position and length)
	 * @param int $nodeWordLength the length of the word along the nodes.
	 * @param int $currentNode the node we are currently at.
	 * @param int $distance the distance along the word leading to the current node.
	 * @param int $wordStart the start of the currently searched word.
     * @return void
	 */
    private function findRemainingClones(
        array &$clonePositions,
        int $nodeWordLength,
        int $currentNode,
        int $distance,
        int $wordStart)
    {
		for ($nextNode = $this->nodeChildFirst[$currentNode]; $nextNode >= 0; $nextNode = $this->nodeChildNext[$nextNode]) {
			$node = $this->nodeChildNode[$nextNode];
			$this->findRemainingClones($clonePositions, $nodeWordLength, $node, $distance
					+ $this->nodeWordEnd[$node] - $this->nodeWordBegin[$node], $wordStart);
		}

		if ($this->nodeChildFirst[$currentNode] < 0) {
			$start = count($this->word) - $distance - $nodeWordLength;
			if ($start != $wordStart) {
				$clonePositions[] = [$start, $nodeWordLength];
			}
		}
	}

	/**
	 * This should return true, if the provided character is not allowed to
	 * match with anything else (e.g. is a sentinel).
	 */
	//protected abstract boolean mayNotMatch(Object character);
    protected function mayNotMatch(JavaObjectInterface $character)
    {
        return $character instanceof Sentinel;
    }

	/**
	 * This method is called whenever the {@link #MAX_LENGTH} is to small and
	 * hence the {@link #edBuffer} was not large enough. This may cause that a
	 * really large clone is reported in multiple chunks of size
	 * {@link #MAX_LENGTH} and potentially minor parts of such a clone might be
	 * lost.
	 */
	//@SuppressWarnings("unused")
	//protected void reportBufferShortage(int leafStart, int leafLength) {
		// empty base implementation
	//}
    protected function reportBufferShortage(int $leafStart, int $leafLength) {
        echo "Encountered buffer shortage: " . $leafStart . " " . $leafLength . "\n";
    }

    /*
	protected class CloneConsumer implements ICloneReporter {

		private final MultiplexingCloneClassesCollection results = new MultiplexingCloneClassesCollection();

		public CloneConsumer() {
			if (top == INFINITE) {
				results.addCollection(new ArrayList<CloneClass>());
			} else {
				results.addCollection(boundedCollection(NORMALIZED_LENGTH));
				results.addCollection(boundedCollection(CARDINALITY));
				results.addCollection(boundedCollection(VOLUME));
			}
		}

		private BoundedPriorityQueue<CloneClass> boundedCollection(
				ECloneClassComparator dimension) {
			return new BoundedPriorityQueue<CloneClass>(top, dimension);
		}

		protected CloneClass currentCloneClass;

		@Override
		public void startCloneClass(int normalizedLength) {
			currentCloneClass = new CloneClass(normalizedLength,
					idProvider.provideId());
		}

		@Override
		public Clone addClone(int globalPosition, int length)
				throws ConQATException {
			// compute length of clone in lines
			Unit firstUnit = units.get(globalPosition);
			Unit lastUnit = units.get(globalPosition + length - 1);
			List<Unit> cloneUnits = units.subList(globalPosition,
					globalPosition + length);

			ITextElement element = resolveElement(firstUnit
					.getElementUniformPath());
			int startUnitIndexInElement = firstUnit.getIndexInElement();
			int endUnitIndexInElement = lastUnit.getIndexInElement();
			int lengthInUnits = endUnitIndexInElement - startUnitIndexInElement
					+ 1;
			CCSMAssert.isTrue(lengthInUnits >= 0, "Negative length in units!");
			String fingerprint = createFingerprint(globalPosition, length);

			Clone clone = new Clone(idProvider.provideId(), currentCloneClass,
					createCloneLocation(element,
							firstUnit.getFilteredStartOffset(),
							lastUnit.getFilteredEndOffset()),
					startUnitIndexInElement, lengthInUnits, fingerprint);

			if (storeUnits) {
				CloneUtils.setUnits(clone, cloneUnits);
			}

			currentCloneClass.add(clone);

			return clone;
		}

		// Creates the location for a clone.
		private TextRegionLocation createCloneLocation(ITextElement element,
				int filteredStartOffset, int filteredEndOffset)
				throws ConQATException {
			int rawStartOffset = element
					.getUnfilteredOffset(filteredStartOffset);
			int rawEndOffset = element.getUnfilteredOffset(filteredEndOffset);
			int rawStartLine = element
					.convertUnfilteredOffsetToLine(rawStartOffset);
			int rawEndLine = element
					.convertUnfilteredOffsetToLine(rawEndOffset);

			return new TextRegionLocation(element.getLocation(),
					element.getUniformPath(), rawStartOffset, rawEndOffset,
					rawStartLine, rawEndLine);
		}

		protected ITextElement resolveElement(String elementUniformPath) {
			return uniformPathToElement.get(elementUniformPath);
		}

		protected String createFingerprint(int globalPosition, int length) {
			StringBuilder fingerprintBase = new StringBuilder();
			for (int pos = globalPosition; pos < globalPosition + length; pos++) {
				fingerprintBase.append(units.get(pos).getContent());
			}
			return Digester.createMD5Digest(fingerprintBase.toString());
		}

		@Override
		public boolean completeCloneClass() throws ConQATException {
			boolean constraintsSatisfied = constraints
					.allSatisfied(currentCloneClass);

			if (constraintsSatisfied) {
				results.add(currentCloneClass);
			}

			return constraintsSatisfied;
		}

		public List<CloneClass> getCloneClasses() {
			return results.getCloneClasses();
		}
	}
    */
}
