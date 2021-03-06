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
class SuffixTreeHashTable {

	/**
	 * These numbers were taken from
	 * http://planetmath.org/encyclopedia/GoodHashTablePrimes.html
	 */
	private $allowedSizes = [53, 97, 193, 389, 769, 1543,
			3079, 6151, 12289, 24593, 49157, 98317, 196613, 393241, 786433,
			1572869, 3145739, 6291469, 12582917, 25165843, 50331653, 100663319,
			201326611, 402653189, 805306457, 1610612741];

	/** The size of the hash table. */
	private $tableSize = [];

	/** Storage space for the node part of the key */
	private $keyNodes = [];

	/** Storage space for the character part of the key. */
	private $keyChars = [];

	/** Storage space for the result node. */
	private $resultNodes = [];

	/** Debug info: number of stored nodes. */
	private $_numStoredNodes = 0;

	/** Debug info: number of calls to find so far. */
	private $_numFind = 0;

	/** Debug info: number of collisions (i.e. wrong finds) during find so far. */
	private $_numColl = 0;

	/**
	 * Creates a new hash table for the given number of nodes. Trying to add
	 * more nodes will result in worse performance down to entering an infinite
	 * loop on some operations.
	 */
	public function __construct($numNodes) {
		$minSize = (int) ceil(1.5 * $numNodes);
		$sizeIndex = 0;
		while ($this->allowedSizes[$sizeIndex] < $minSize) {
			++$sizeIndex;
		}
		$this->tableSize = $this->allowedSizes[$sizeIndex];

		$this->keyNodes = [$this->tableSize];
        // obj?
		$this->keyChars = [$this->tableSize];
		$this->resultNodes = [$this->tableSize];
	}

	/**
	 * Returns the position of the (node,char) key in the hash map or the
	 * position to insert it into if it is not yet in.
	 */
	private function hashFind(int $keyNode, object $keyChar) {
		++$this->_numFind;
		$hash = $keyChar->hashCode();
		$pos = $this->posMod($this->primaryHash($keyNode, $hash));
		$secondary = $this->secondaryHash($keyNode, $hash);
        echo 'pos = ' . $pos;
		while ($this->keyChars[$pos] != null) {
			if ($this->keyNodes[$pos] == $keyNode && $keyChar->equals($this->keyChars[$pos])) {
				break;
			}
			++$this->_numColl;
			$pos = ($pos + $secondary) % $this->tableSize;
		}
		return $pos;
	}

	/**
	 * Returns the next node for the given (node, character) key pair or a
	 * negative value if no next node is stored for this key.
	 */
	public function get(int $keyNode, object $keyChar) {
		$pos = $this->hashFind($keyNode, $keyChar);
		if ($this->keyChars[$pos] == null) {
			return -1;
		}
		return $this->resultNodes[$pos];
	}

	/** Inserts the given result node for the (node, character) key pair. */
	public function put(int $keyNode, $keyChar, int $resultNode) {
		$pos = $this->hashFind($keyNode, $keyChar);
		if ($this->keyChars[$pos] == null) {
			++$this->_numStoredNodes;
			$this->keyChars[$pos] = $keyChar;
			$this->keyNodes[$pos] = $keyNode;
		}
		$this->resultNodes[$pos] = $resultNode;
	}

	/** Returns the primary hash value for a (node, character) key pair. */
	private function primaryHash(int $keyNode, int $keyCharHash) {
		return $keyCharHash ** (13 * $keyNode);
	}

	/** Returns the secondary hash value for a (node, character) key pair. */
	private function secondaryHash(int $keyNode, int $keyCharHash) {
		$result = $this->posMod(($keyCharHash ** (1025 * $keyNode)));
		if ($result == 0) {
			return 2;
		}
		return $result;
	}

	/**
	 * Returns the smallest non-negative number congruent to x modulo
	 * {@link #tableSize}.
	 */
	private function posMod($x) {
		$x = $x % $this->tableSize;
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
	 * @param nodeFirstIndex
	 *            an array giving for each node the index where the first child
	 *            will be stored (or -1 if it has no children).
	 * @param nodeNextIndex
	 *            this array gives the next index of the child list or -1 if
	 *            this is the last one.
	 * @param nodeChild
	 *            this array stores the actual name (=number) of the mode in the
	 *            child list.
	 * @throws ArrayIndexOutOfBoundsException
	 *             if any of the given arrays was too small.
	 */
	public function extractChildLists(array $nodeFirstIndex, array $nodeNextIndex, array $nodeChild) {
		Arrays.fill($nodeFirstIndex, -1);
		$free = 0;
		for ($i = 0; $i < $this->tableSize; ++$i) {
			if ($this->keyChars[$i] != null) {
				// insert keyNodes[$i] -> $this->resultNodes[$i]
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
	public function _printDebugInfo() {
		echo ("STHashMap info: ");
		echo ("  Table size: " + $this->tableSize);
		echo ("  Contained entries: " + $this->_numStoredNodes);
		echo ("  Fill factor: "
				+ ((double) $this->_numStoredNodes / $this->tableSize));
		echo ("  Number of finds: " + $this->_numFind);
		echo ("  Number of collisions: " + $this->_numColl);
	}
}
