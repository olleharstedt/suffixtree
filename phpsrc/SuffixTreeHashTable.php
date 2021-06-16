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
 * The hash table used for the {@link SuffixTree} class. It is specifically
 * written and optimized for its implementation and is thus probably of little
 * use for any other application.
 * <p>
 * It hashes from (node, character) pairs to the next node, where nodes are
 * represented by integers and the type of characters is determined by the
 * generic parameter.
 * 
 * @author Benjamin Hummel
 * @author $Author: juergens $
 * 
 * @version $Revision: 34670 $
 * @ConQAT.Rating GREEN Hash: 6A7A830078AF0CA9C2D84C148F336DF4
 */
class SuffixTreeHashTable
{
	/**
	 * These numbers were taken from
	 * http://planetmath.org/encyclopedia/GoodHashTablePrimes.html
     * @var int[]
	 */
	private $allowedSizes = [ 53, 97, 193, 389, 769, 1543,
			3079, 6151, 12289, 24593, 49157, 98317, 196613, 393241, 786433,
			1572869, 3145739, 6291469, 12582917, 25165843, 50331653, 100663319,
			201326611, 402653189, 805306457, 1610612741 ];

    /** The size of the hash table.
        * @var int */
	private $tableSize;

    /** Storage space for the node part of the key
        * @var int[] */
	private $keyNodes;

    /** Storage space for the character part of the key.
        * @var object[] */
	private $keyChars;

    /** Storage space for the result node.
        * @var int[] */
	private $resultNodes;

    /** Debug info: number of stored nodes.
        * @var int */
	private $_numStoredNodes = 0;

    /** Debug info: number of calls to find so far.
        * @var int */
	private $_numFind = 0;

    /** Debug info: number of collisions (i.e. wrong finds) during find so far.
        * @var int */
	private $_numColl = 0;

	/**
	 * Creates a new hash table for the given number of nodes. Trying to add
	 * more nodes will result in worse performance down to entering an infinite
	 * loop on some operations.
     *
     * @param int $numNodes
	 */
    public function __construct(int $numNodes)
    {
		$minSize = (int) ceil(1.5 * $numNodes);
		$sizeIndex = 0;
		while ($this->allowedSizes[$sizeIndex] < $minSize) {
			$sizeIndex++;
		}
		$this->tableSize = $this->allowedSizes[$sizeIndex];

		$this->keyNodes = array_fill(0, $this->tableSize, 0);
		$this->keyChars = array_fill(0, $this->tableSize, null);
		$this->resultNodes = array_fill(0, $this->tableSize, 0);
	}

	/**
	 * Returns the position of the (node,char) key in the hash map or the
	 * position to insert it into if it is not yet in.
     *
     * @param int $keyNode
     * @param JavaObjectInterface $keyChar
     * @return int
	 */
    private function hashFind(int $keyNode, JavaObjectInterface $keyChar)
    {
		++$this->_numFind;
        /** @var int */
		$hash = $keyChar->hashCode();
        //echo $keyChar->content . ' ';
        //echo $hash . ' ';
        /** @var int */
		$pos = $this->posMod($this->primaryHash($keyNode, $hash));
        echo $pos . ' ';
        /** @var int */
		$secondary = $this->secondaryHash($keyNode, $hash);
		while ($this->keyChars[$pos] !== null) {
			if ($this->keyNodes[$pos] === $keyNode && $keyChar->equals($this->keyChars[$pos])) {
				break;
			}
			++$this->_numColl;
			$pos = ($pos + $secondary) % $this->tableSize;
            //echo $pos . ' ';
		}
        //echo $pos . ' ';
		return $pos;
	}

	/**
	 * Returns the next node for the given (node, character) key pair or a
	 * negative value if no next node is stored for this key.
     *
     * @return int
	 */
    public function get(int $keyNode, JavaObjectInterface $keyChar): int
    {
		$pos = $this->hashFind($keyNode, $keyChar);
		if ($this->keyChars[$pos] === null) {
			return -1;
		}
		return $this->resultNodes[$pos];
	}

    /** Inserts the given result node for the (node, character) key pair.
        * @return void */
    public function put(int $keyNode, JavaObjectInterface $keyChar, int $resultNode)
    {
		$pos = $this->hashFind($keyNode, $keyChar);
		if ($this->keyChars[$pos] == null) {
			++$this->_numStoredNodes;
			$this->keyChars[$pos] = $keyChar;
			$this->keyNodes[$pos] = $keyNode;
		}
		$this->resultNodes[$pos] = $resultNode;
	}

    /** Returns the primary hash value for a (node, character) key pair.
        * @return int */
    private function primaryHash(int $keyNode, int $keyCharHash)
    {
		$res =  $keyCharHash ^ (13 * $keyNode);
        return $res;
	}

    /**
     * Returns the secondary hash value for a (node, character) key pair.
     *
     * @return int
     */
    private function secondaryHash(int $keyNode, int $keyCharHash)
    {
		$result = $this->posMod(($keyCharHash ^ (1025 * $keyNode)));
		if ($result == 0) {
			return 2;
		}
		return $result;
	}

	/**
	 * Returns the smallest non-negative number congruent to x modulo
	 * {@link #tableSize}.
     * @return int
	 */
    private function posMod(int $x)
    {
		$x %= $this->tableSize;
		if ($x < 0) {
			$x += $this->tableSize;
		}
		return $x;
	}

	/**
	 * Extracts the list of child nodes for each node from the hash table
	 * entries as a linked list. All arrays are expected to be initially empty
	 * and of suitable size (i.e. for <em>n</em> nodes it should have size
	 * <em>n</em> given that nodes are numbered 0 to n-1). Those arrays will be
	 * filled from this method.
	 * <p>
	 * The method is package visible, as it is tighly coupled to the
	 * {@link SuffixTree} class.
	 * 
	 * @param int[] nodeFirstIndex an array giving for each node the index where the first child
	 *            will be stored (or -1 if it has no children).
	 * @param int[] nodeNextIndex this array gives the next index of the child list or -1 if
	 *            this is the last one.
	 * @param int[] nodeChild this array stores the actual name (=number) of the mode in the
	 *            child list.
     * @return void
	 * @throws ArrayIndexOutOfBoundsException if any of the given arrays was too small.
	 */
    public function extractChildLists(array &$nodeFirstIndex, array &$nodeNextIndex, array &$nodeChild)
    {
		// Instead of Arrays.fill($nodeFirstIndex, -1);
        foreach ($nodeFirstIndex as $k => $v) {
            $nodeFirstIndex[$k] = -1;
        }
		$free = 0;
		for ($i = 0; $i < $this->tableSize; ++$i) {
			if ($this->keyChars[$i] !== null) {
				// insert $this->keyNodes[$i] -> $this->resultNodes[$i]
				$nodeChild[$free] = $this->resultNodes[$i];
				$nodeNextIndex[$free] = $nodeFirstIndex[$this->keyNodes[$i]];
				$nodeFirstIndex[$this->keyNodes[$i]] = $free++;
			}
		}
	}

	/**
	 * Prints some internal statistics, such as fill factor and collisions to
	 * std err.
	 */
    /*
	public void _printDebugInfo() {
		System.err.println("STHashMap info: ");
		System.err.println("  Table size: " + tableSize);
		System.err.println("  Contained entries: " + $this->_numStoredNodes);
		System.err.println("  Fill factor: "
				+ ((double) $this->_numStoredNodes / tableSize));
		System.err.println("  Number of finds: " + $this->_numFind);
		System.err.println("  Number of collisions: " + $this->_numColl);
	}
     */
}
