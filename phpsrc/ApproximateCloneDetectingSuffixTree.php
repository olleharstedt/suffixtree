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
     * @todo Add options:
     *   --min-tokens
     *   --min-lines
     *   --edit-distance
     * @todo Possibly add consumer from original code.
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
		$this->cloneInfos = [];

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

		for ($index = 0; $index <= count($this->word); ++$index) {
			$existingClones = $this->cloneInfos[$index] ?? null;
			if ($existingClones != null) {
                foreach ($existingClones as $ci) {
                    // length = number of tokens
                    // TODO: min token length
                    if ($ci->length > 25) {
                        /** @var CloneInfo */
                        $previousCi = $map[$ci->token->line] ?? null;
                        if ($previousCi == null) {
                            $map[$ci->token->line] =  $ci;
                        } else if ($ci->length > $previousCi->length) {
                            $map[$ci->token->line] = $ci;
                        }
                        /** @var int[] */
                        $others = $ci->otherClones->extractFirstList();
                        for ($j = 0; $j < count($others); $j++) {
                            $otherStart = $others[$j];
                            /** @var PhpToken */
                            $t = $this->word[$otherStart];
                        }
                    }
				}
			}
		}

        /** @var CloneInfo[] */
        $values = array_values($map);
        usort($values, function ($a, $b) { return $b->length - $a->length;});
        printf(
            "\nFound %d clones with %d duplicated lines in %d files:\n\n",
            count($values),
            0,  // TODO: Fix
            0
        );
        // TODO: Filter overlapping clones.
        for ($i = 0; $i < count($values); $i++) {
            /** @var CloneInfo */
            $ci = $values[$i];
            try {
                /** @var PhpToken */
                $lastToken = $this->word[$ci->position + $ci->length];
                $lines = $lastToken->line - $ci->token->line;
                printf(
                    "  - %s:%d-%d (%d lines)\n",
                    $ci->token->file,
                    $ci->token->line,
                    $ci->token->line + $lines - 1,
                    $lines
                );
            } catch(IndexOutOfBoundsException $e) {
                printf("index out of bounds, ci.position = %d, ci.length = %d", $ci->position, $ci->length);
            }
            /** @var int[] */
            $others = $ci->otherClones->extractFirstList();
            for ($j = 0; $j < count($others); $j++) {
                $otherStart = $others[$j];
                /** @var PhpToken */
                $t = $this->word[$otherStart];
                /** @var PhpToken */
                $lastToken = $this->word[$ci->position + $ci->length];
                $lines = $lastToken->line - $ci->token->line;
                printf(
                        "    %s:%d-%d\n",
                        $t->file,
                        $t->line,
                        $t->line + $lines - 1
                );
            }
            print("\n");
        }
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
				&& $this->word[$wordPosition + $iBest] != $this->word[$this->nodeWordBegin[$node] + $jBest]
				&& $this->word[$wordPosition + $iBest]->equals(
						$this->word[$this->nodeWordBegin[$node] + $jBest])) {
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
			$this->reportClone($wordStart, $wordPosition + $iBest, $node, $jBest, $nodeWordLength + $jBest);
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
				$best = min(
						$best,
						$this->fillEDBuffer($k, $currentLength, $wordPosition,
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

        /** @var PairList */
		$otherClones = new PairList();
        $this->findRemainingClones(
            $otherClones,
            $nodeWordLength,
            $currentNode,
            $this->nodeWordEnd[$currentNode] - $this->nodeWordBegin[$currentNode] - $nodeWordPos,
            $wordBegin
        );

		$occurrences = 1 + $otherClones->size();

		// check whether we may start from here
        /** @var PhpToken */
        $t = $this->word[$wordBegin];
        /** @var CloneInfo */
		$newInfo = new CloneInfo($length, $wordBegin, $occurrences, $t, $otherClones);
		for ($index = max(0, $wordBegin - $this->INDEX_SPREAD + 1); $index <= $wordBegin; ++$index) {
            /** @var CloneInfo */
			$existingClones = $this->cloneInfos[$index] ?? null;
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

		// add clone to $otherClones to avoid getting more duplicates
		for ($i = $wordBegin; $i < $wordEnd; $i += $this->INDEX_SPREAD) {
			$this->cloneInfos[$i][] = new CloneInfo($length - ($i - $wordBegin), $wordBegin, $occurrences, $t, $otherClones);
		}
        /** @var PhpToken */
        $t = $this->word[$wordBegin];
		for ($clone = 0; $clone < $otherClones->size(); ++$clone) {
			$start = $otherClones->getFirst($clone);
			$otherLength = $otherClones->getSecond($clone);
            for ($j = 0; $j < $otherLength; $j++) {
                /** @var PhpToken */
                $r = $this->word[$j + $start];
            }
			for ($i = 0; $i < $otherLength; $i += $this->INDEX_SPREAD) {
				//$this->cloneInfos.add($start + $i, new CloneInfo($otherLength - $i, $wordBegin, occurrences, $t, $otherClones));
				$this->cloneInfos[$start + $i][] = new CloneInfo($otherLength - $i, $wordBegin, $occurrences, $t, $otherClones);
			}
		}
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
        PairList $clonePositions,
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
				$clonePositions->add($start, $nodeWordLength);
			}
		}
	}

	/**
	 * This should return true, if the provided character is not allowed to
	 * match with anything else (e.g. is a sentinel).
	 */
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
    protected function reportBufferShortage(int $leafStart, int $leafLength) {
        echo "Encountered buffer shortage: " . $leafStart . " " . $leafLength . "\n";
    }
}
