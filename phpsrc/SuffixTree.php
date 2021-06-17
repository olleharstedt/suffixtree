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
 * Efficient linear time constructible suffix tree using Ukkonen's online
 * construction algorithm (E. Ukkonen: "On-line construction of suffix trees").
 * Most of the comments reference this paper and it might be hard to follow
 * without knowing at least the basics of it.
 * <p>
 * We use some conventions which are slightly different from the paper however:
 * <ul>
 * <li>The names of the variables are different, but we give a translation into
 * Ukkonen's names.</li>
 * <li>Many variables are made "global" by realizing them as fields. This way we
 * can easily deal with those tuple return values without constructing extra
 * classes.</li>
 * <li>String indices start at 0 (not at 1).</li>
 * <li>Substrings are marked by the first index and the index after the last one
 * (just as in C++ STL) instead of the first and the last index (i.e. intervals
 * are right-open instead of closed). This makes it more intuitive to express
 * the empty string (i.e. (i,i) instead of (i,i-1)).</li>
 * </ul>
 * <p>
 * Everything but the construction itself is protected to simplify increasing
 * its functionality by subclassing but without introducing new method calls.
 * 
 * @author Benjamin Hummel
 * @author $Author: kinnen $
 * 
 * @version $Revision: 41751 $
 * @ConQAT.Rating GREEN Hash: 4B2EF0606B3085A6831764ED042FF20D
 */
class SuffixTree
{
    /**
     * Infinity in this context.
     * @var int 
     */
	protected $INFTY;

    /** The word we are working on.
        * @var array */
	protected $word;

    /** The number of nodes created so far.
        * @var int */
	protected $numNodes = 0;

	/**
	 * For each node this holds the index of the first character of
	 * {@link #word} labeling the transition <b>to</b> this node. This
	 * corresponds to the <em>k</em> for a transition used in Ukkonen's paper.
     *
     * @var int[]
	 */
	protected $nodeWordBegin;

	/**
	 * For each node this holds the index of the one after the last character of
	 * {@link #word} labeling the transition <b>to</b> this node. This
	 * corresponds to the <em>p</em> for a transition used in Ukkonen's paper.
     *
     * @var int[]
	 */
	protected $nodeWordEnd;

    /** For each node its suffix link (called function <em>f</em> by Ukkonen).
        * @var int[] */
	protected $suffixLink;

	/**
	 * The next node function realized as a hash table. This corresponds to the
	 * <em>g</em> function used in Ukkonen's paper.
     *
     * @var SuffixTreeHashTable
	 */
	protected $nextNode;

	/**
	 * An array giving for each node the index where the first child will be
	 * stored (or -1 if it has no children). It is initially empty and will be
	 * filled "on demand" using
	 * {@link org.conqat.engine.code_clones.detection.suffixtree.SuffixTreeHashTable#extractChildLists(int[], int[], int[])}
	 * .
     *
     * @var int[]
	 */
	protected $nodeChildFirst;

	/**
	 * This array gives the next index of the child list or -1 if this is the
	 * last one. It is initially empty and will be filled "on demand" using
	 * {@link org.conqat.engine.code_clones.detection.suffixtree.SuffixTreeHashTable#extractChildLists(int[], int[], int[])}
	 * .
     *
     * @var int[]
	 */
	protected $nodeChildNext;

	/**
	 * This array stores the actual name (=number) of the mode in the child
	 * list. It is initially empty and will be filled "on demand" using
	 * {@link org.conqat.engine.code_clones.detection.suffixtree.SuffixTreeHashTable#extractChildLists(int[], int[], int[])}
	 * .
     *
     * @var int[]
	 */
	protected $nodeChildNode;

	/**
	 * The node we are currently at as a "global" variable (as it is always
	 * passed unchanged). This is called <i>s</i> in Ukkonen's paper.
     *
     * @var int
	 */
	private $currentNode = 0;

	/**
	 * Beginning of the word part of the reference pair. This is kept "global"
	 * (in constrast to the end) as this is passed unchanged to all functions.
	 * Ukkonen calls this <em>k</em>.
     *
     * @var int
	 */
	private $refWordBegin = 0;

	/**
	 * This is the new (or old) explicit state as returned by
	 * {@link #testAndSplit(int, Object)}. Ukkonen calls this <em>r</em>.
     *
     * @var int
	 */
	private $explicitNode;

	/**
	 * Create a new suffix tree from a given word. The word given as parameter
	 * is used internally and should not be modified anymore, so copy it before
	 * if required.
     *
     * @param array $word
	 */
    public function __construct(array $word)
    {
		$this->word = $word;
		$size = count($word);
		$this->INFTY = $size;

		$expectedNodes = 2 * $size;
        $this->nodeWordBegin = array_fill(0, $expectedNodes, 0);
		$this->nodeWordEnd = array_fill(0, $expectedNodes, 0);
		$this->suffixLink = array_fill(0, $expectedNodes, 0);
		$this->nextNode = new SuffixTreeHashTable($expectedNodes);

		$this->createRootNode();

		for ($i = 0; $i < $size; ++$i) {
			$this->update($i);
            $this->canonize($i + 1);
		}
	}

    /**
     * Creates the root node.
     *
     * @return void
     */
    private function createRootNode()
    {
		$this->numNodes = 1;
		$this->nodeWordBegin[0] = 0;
		$this->nodeWordEnd[0] = 0;
		$this->suffixLink[0] = -1;
	}

	/**
	 * The <em>update</em> function as defined in Ukkonen's paper. This inserts
	 * the character at charPos into the tree. It works on the canonical
	 * reference pair ({@link #currentNode}, ({@link #refWordBegin}, charPos)).
     *
     * @param int $charPos
     * @return void
	 */
	private function update(int $charPos) {
		$lastNode = 0;
		while (!$this->testAndSplit($charPos, $this->word[$charPos])) {
			$newNode = $this->numNodes++;
			$this->nodeWordBegin[$newNode] = $charPos;
			$this->nodeWordEnd[$newNode] = $this->INFTY;
			$this->nextNode->put($this->explicitNode, $this->word[$charPos], $newNode);

			if ($lastNode != 0) {
				$this->suffixLink[$lastNode] = $this->explicitNode;
			}
			$lastNode = $this->explicitNode;
			$this->currentNode = $this->suffixLink[$this->currentNode];
			$this->canonize($charPos);
		}
		if ($lastNode != 0) {
			$this->suffixLink[$lastNode] = $this->currentNode;
		}
	}

	/**
	 * The <em>test-and-split</em> function as defined in Ukkonen's paper. This
	 * checks whether the state given by the canonical reference pair (
	 * {@link #currentNode}, ({@link #refWordBegin}, refWordEnd)) is the end
	 * point (by checking whether a transition for the
	 * <code>nextCharacter</code> exists). Additionally the state is made
	 * explicit if it not already is and this is not the end-point. It returns
	 * true if the end-point was reached. The newly created (or reached)
	 * explicit node is returned in the "global" variable.
     *
     * @param int $refWordEnd
     * @param object $nextCharacter
     * @return boolean
	 */
    private function testAndSplit(int $refWordEnd, JavaObjectInterface $nextCharacter)
    {
		if ($this->currentNode < 0) {
			// trap state is always end state
			return true;
		}

		if ($refWordEnd <= $this->refWordBegin) {
			if ($this->nextNode->get($this->currentNode, $nextCharacter) < 0) {
				$this->explicitNode = $this->currentNode;
				return false;
			}
			return true;
		}

        /** @var int */
		$next = $this->nextNode->get($this->currentNode, $this->word[$this->refWordBegin]);
		if ($nextCharacter->equals($this->word[$this->nodeWordBegin[$next] + $refWordEnd - $this->refWordBegin])) {
			return true;
		}

		// not an end-point and not explicit, so make it explicit.
		$this->explicitNode = $this->numNodes++;
		$this->nodeWordBegin[$this->explicitNode] = $this->nodeWordBegin[$next];
		$this->nodeWordEnd[$this->explicitNode] = $this->nodeWordBegin[$next] + $refWordEnd - $this->refWordBegin;
		$this->nextNode->put($this->currentNode, $this->word[$this->refWordBegin], $this->explicitNode);

		$this->nodeWordBegin[$next] += $refWordEnd - $this->refWordBegin;
		$this->nextNode->put($this->explicitNode, $this->word[$this->nodeWordBegin[$next]], $next);
		return false;
	}

	/**
	 * The <em>canonize</em> function as defined in Ukkonen's paper. Changes the
	 * reference pair (currentNode, (refWordBegin, refWordEnd)) into a canonical
	 * reference pair. It works on the "global" variables {@link #currentNode}
	 * and {@link #refWordBegin} and the parameter, writing the result back to
	 * the globals.
	 * 
	 * @param int $refWordEnd one after the end index for the word of the reference pair.
     * @return void
	 */
    private function canonize(int $refWordEnd): void
    {
		if ($this->currentNode === -1) {
			// explicitly handle trap state
			$this->currentNode = 0;
			$this->refWordBegin++;
		}

		if ($refWordEnd <= $this->refWordBegin) {
			// empty word, so already canonical
			return;
		}

        /** @var int */
        $next = $this->nextNode->get(
            $this->currentNode,
            $this->word[$this->refWordBegin]
        );
		while ($this->nodeWordEnd[$next] - $this->nodeWordBegin[$next] <= $refWordEnd
				- $this->refWordBegin) {
                $this->refWordBegin += $this->nodeWordEnd[$next] - $this->nodeWordBegin[$next];
                $this->currentNode = $next;
                if ($refWordEnd > $this->refWordBegin) {
                    $next = $this->nextNode->get($this->currentNode, $this->word[$this->refWordBegin]);
                } else {
                    break;
                }
        }
    }

	/**
	 * This method makes sure the child lists are filled (required for
	 * traversing the tree).
     *
     * @return void
	 */
    protected function ensureChildLists()
    {
		if ($this->nodeChildFirst == null || count($this->nodeChildFirst) < $this->numNodes) {
			$this->nodeChildFirst = array_fill(0, $this->numNodes, 0);
			$this->nodeChildNext = array_fill(0, $this->numNodes, 0);
			$this->nodeChildNode = array_fill(0, $this->numNodes, 0);
			$this->nextNode->extractChildLists($this->nodeChildFirst, $this->nodeChildNext, $this->nodeChildNode);
		}
	}

	/**
	 * Returns whether the given word is contained in the string given at
	 * construction time.
     *
     * @param array $find
     * @return boolean
	 */
	public function containsWord(array $find) {
		$node = 0;
		$findSize = count($find);
		for ($i = 0; $i < $findSize;) {
            /** @var int */
			$next = $this->nextNode->get($node, $find[$i]);
			if ($next < 0) {
				return false;
			}
			for ($j = $this->nodeWordBegin[$next]; $j < $this->nodeWordEnd[$next] && $i < $findSize; ++$i, ++$j) {
				if (!$this->word[$j]->equals($find[$i])) {
					return false;
				}
			}
			$node = $next;
		}
		return true;
	}
}
