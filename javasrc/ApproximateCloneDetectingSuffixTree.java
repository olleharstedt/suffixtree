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

import java.util.*;
import java.io.*;
import java.nio.charset.*;
import java.nio.file.*;
import java.util.ArrayList;
import java.util.Scanner;
import java.security.*;
import org.json.JSONObject;
import org.json.JSONArray;

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
public abstract class ApproximateCloneDetectingSuffixTree extends SuffixTree {

	/** The number of leaves reachable from the given node (1 for leaves). */
	private final int[] leafCount;

	/** This is the distance between two entries in the {@link #cloneInfos} map. */
	private static final int INDEX_SPREAD = 10;

	/** This map stores for each position the relevant clone infos. */
	private final ListMap<Integer, CloneInfo> cloneInfos = new ListMap<Integer, CloneInfo>();

	/**
	 * The maximal length of a clone. This influences the size of the
	 * (quadratic) {@link #edBuffer}.
	 */
	private static final int MAX_LENGTH = 1024;

	/** Buffer used for calculating edit distance. */
	private final int[][] edBuffer = new int[MAX_LENGTH][MAX_LENGTH];

	/** The minimal length of clones to return. */
	protected int minLength;

	/** Number of units that must be equal at the start of a clone */
	private int headEquality;

	/**
	 * Create a new suffix tree from a given word. The word given as parameter
	 * is used internally and should not be modified anymore, so copy it before
	 * if required.
	 * <p>
	 * This only word correctly if the given word is closed using a sentinel
	 * character.
	 */
	public ApproximateCloneDetectingSuffixTree(List<?> word) {
		super(word);
		ensureChildLists();
		leafCount = new int[numNodes];
		initLeafCount(0);
	}

	/**
	 * Initializes the {@link #leafCount} array which given for each node the
	 * number of leaves reachable from it (where leaves obtain a value of 1).
	 */
	private void initLeafCount(int node) {
		leafCount[node] = 0;
		for (int e = nodeChildFirst[node]; e >= 0; e = nodeChildNext[e]) {
			initLeafCount(nodeChildNode[e]);
			leafCount[node] += leafCount[nodeChildNode[e]];
		}
		if (leafCount[node] == 0) {
			leafCount[node] = 1;
		}
	}

    /**
     * TODO: Add options:
     *   --min-tokens
     *   --min-lines
     *   --edit-distance
     */
    public static void main(String[] args) throws ConQATException, IOException {
        //String input = Files.readString(Paths.get("QuestionTheme.php"), StandardCharsets.US_ASCII);
        //input.replaceAll("\\s+","");
        //String input = "bla bla bla test bla bla bla bla bla mooooo something else";
        //List<Character> word = SuffixTreeTest.stringToList(input);

        //String a = "LimeSurvey";
        //a.chars().forEach(c -> System.out.println(0 + c));
        //System.out.println(a.hashCode());
        //System.out.println(10 ^ 0);
        //System.exit(0);

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

	/**
	 * Finds all clones in the string (List) used in the constructor.
	 * 
	 * @param minLength the minimal length of a clone in tokens (not lines)
	 * @param maxErrors the maximal number of errors/gaps allowed
	 * @param headEquality the number of elements which have to be the same at the beginning of a clone
	 */
	public void findClones(int minLength, int maxErrors, int headEquality) throws ConQATException {
		this.minLength = minLength;
		this.headEquality = headEquality;
		cloneInfos.clear();

		for (int i = 0; i < word.size(); ++i) {
			// Do quick start, as first character has to match anyway.
			int node = nextNode.get(0, word.get(i));
			if (node < 0 || leafCount[node] <= 1) {
				continue;
			}

			// we know that we have an exact match of at least 'length'
			// characters, as the word itself is part of the suffix tree.
			int length = nodeWordEnd[node] - nodeWordBegin[node];
			int numReported = 0;
			for (int e = nodeChildFirst[node]; e >= 0; e = nodeChildNext[e]) {
				if (matchWord(i, i + length, nodeChildNode[e], length,
						maxErrors)) {
					++numReported;
				}
			}
			if (length >= minLength && numReported != 1) {
				reportClone(i, i + length, node, length, length);
			}
		}

        List<Integer> lengths = new ArrayList<Integer>();
        Map<Integer, CloneInfo> map = new HashMap<>();
        Comparator<CloneInfo> comp = new Comparator<CloneInfo>() {
            @Override
            public int compare(CloneInfo c, CloneInfo d) {
                //return d.token.line - c.token.line;
                return d.length - c.length;
            }
        };
        TreeSet<CloneInfo> tree = new TreeSet<CloneInfo>(comp);

        List<CloneInfo> allClones = new ArrayList<CloneInfo>();
		for (int index = 0; index <= word.size(); ++index) {
			List<CloneInfo> existingClones = cloneInfos.getCollection(index);
			if (existingClones != null) {
				for (CloneInfo ci : existingClones) {
                    // length = number of tokens
                    // TODO: min token length
                    if (ci.length > 25) {
                        //allClones.add(ci);
                        //lengths.add(ci.length);
                        //tree.add(ci);
                        CloneInfo previousCi = map.get(ci.token.line);
                        if (previousCi == null) {
                            map.put(ci.token.line, ci);
                        } else if (ci.length > previousCi.length) {
                            map.put(ci.token.line, ci);
                        }
                        //System.out.println("length = " + ci.length + ", occurrences = " + ci.occurrences);
                        //System.out.println("line = " + ci.token.line);
                        List<Integer> others = ci.otherClones.extractFirstList();
                        for (int j = 0; j < others.size(); j++) {
                            int otherStart = others.get(j);
                            PhpToken t = (PhpToken) word.get(otherStart);
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
            //CloneInfo ci = itr2.next();
            //System.out.println(ci.token.line);
        //}

        //Iterator<CloneInfo> itr2 = tree.iterator();
        //while (itr2.hasNext()) {
            //CloneInfo ci = itr2.next();
            //System.out.println(ci.length);
        //}

        List<CloneInfo> list = new ArrayList<CloneInfo>(map.values());
        Collections.sort(list, (a, b) -> b.length - a.length);
        //Set set = map.entrySet();
        Iterator itr = list.iterator();
        System.out.printf(
            "\nFound %d clones with %d duplicated lines in %d files:\n\n",
            list.size(),
            0,  // TODO: Fix
            0
        );
        // TODO: Filter overlapping clones.
        while(itr.hasNext()) {
        //for (int i = 0; i < keys.size(); i++) {
            //Map.Entry entry = (Map.Entry) itr.next();  
            //CloneInfo ci = (CloneInfo) entry.getValue();
            //CloneInfo ci = (CloneInfo) map.get(keys.get(i));
            CloneInfo ci = (CloneInfo) itr.next();
            try {
                PhpToken lastToken = (PhpToken) word.get(ci.position + ci.length);
                int lines = lastToken.line - ci.token.line;
                System.out.printf(
                    "  - %s:%d-%d (%d lines)\n",
                    ci.token.file,
                    ci.token.line,
                    ci.token.line + lines - 1,
                    lines
                );
            } catch(IndexOutOfBoundsException e) {
                System.out.printf("index out of bounds, ci.position = %d, ci.length = %d", ci.position, ci.length);
            }
            List<Integer> others = ci.otherClones.extractFirstList();
            for (int j = 0; j < others.size(); j++) {
                int otherStart = others.get(j);
                PhpToken t = (PhpToken) word.get(otherStart);
                PhpToken lastToken = (PhpToken) word.get(ci.position + ci.length);
                int lines = lastToken.line - ci.token.line;
                System.out.printf(
                        "    %s:%d-%d\n",
                        t.file,
                        t.line,
                        t.line + lines - 1
                );
            }
            System.out.println("");
        }
	}

	/**
	 * Performs the approximative matching between the input word and the tree.
	 * 
	 * @param wordStart
	 *            the start position of the currently matched word (position in
	 *            the input word).
	 * @param wordPosition
	 *            the current position along the input word.
	 * @param node
	 *            the node we are currently at (i.e. the edge leading to this
	 *            node is relevant to us).
	 * @param nodeWordLength
	 *            the length of the word found along the nodes (this may be
	 *            different from the length along the input word due to gaps).
	 * @param maxErrors
	 *            the number of errors still allowed.
	 * @return whether some clone was reported
	 */
	private boolean matchWord(int wordStart, int wordPosition, int node,
			int nodeWordLength, int maxErrors) throws ConQATException {

		// We are aware that this method is longer than desirable for code
		// reading. However, we currently do not see a refactoring that has a
		// sensible cost-benefit ratio. Suggestions are welcome!

		// self match?
		if (leafCount[node] == 1 && nodeWordBegin[node] == wordPosition) {
			return false;
		}

		int currentNodeWordLength = Math.min(nodeWordEnd[node]
				- nodeWordBegin[node], MAX_LENGTH - 1);
		// do min edit distance
		int currentLength = calculateMaxLength(wordStart, wordPosition, node,
				maxErrors, currentNodeWordLength);

		if (currentLength == 0) {
			return false;
		}

		if (currentLength >= MAX_LENGTH - 1) {
			reportBufferShortage(nodeWordBegin[node], currentNodeWordLength);
		}

		// calculate cheapest match
		int best = maxErrors + 42;
		int iBest = 0;
		int jBest = 0;
		for (int k = 0; k <= currentLength; ++k) {
			int i = currentLength - k;
			int j = currentLength;
			if (edBuffer[i][j] < best) {
				best = edBuffer[i][j];
				iBest = i;
				jBest = j;
			}

			i = currentLength;
			j = currentLength - k;
			if (edBuffer[i][j] < best) {
				best = edBuffer[i][j];
				iBest = i;
				jBest = j;
			}
		}

		while (wordPosition + iBest < word.size()
				&& jBest < currentNodeWordLength
				&& word.get(wordPosition + iBest) != word
						.get(nodeWordBegin[node] + jBest)
				&& word.get(wordPosition + iBest).equals(
						word.get(nodeWordBegin[node] + jBest))) {
			++iBest;
			++jBest;
		}

		int numReported = 0;
		if (currentLength == currentNodeWordLength) {
			// we may proceed
			for (int e = nodeChildFirst[node]; e >= 0; e = nodeChildNext[e]) {
				if (matchWord(wordStart, wordPosition + iBest,
						nodeChildNode[e], nodeWordLength + jBest, maxErrors
								- best)) {
					++numReported;
				}
			}
		}

		// do not report locally if had reports in exactly one subtree (would be
		// pure subclone)
		if (numReported == 1) {
			return true;
		}

		// disallow tail changes
		while (iBest > 0
				&& jBest > 0
				&& !word.get(wordPosition + iBest - 1).equals(
						word.get(nodeWordBegin[node] + jBest - 1))) {

			if (iBest > 1
					&& word.get(wordPosition + iBest - 2).equals(
							word.get(nodeWordBegin[node] + jBest - 1))) {
				--iBest;
			} else if (jBest > 1
					&& word.get(wordPosition + iBest - 1).equals(
							word.get(nodeWordBegin[node] + jBest - 2))) {
				--jBest;
			} else {
				--iBest;
				--jBest;
			}
		}

		// report if real clone
		if (iBest > 0 && jBest > 0) {
			numReported += 1;
			reportClone(wordStart, wordPosition + iBest, node, jBest,
					nodeWordLength + jBest);
		}

		return numReported > 0;
	}

	/**
	 * Calculates the maximum length we may take along the word to the current
	 * node (respecting the number of errors to make). *
	 * 
	 * @param wordStart
	 *            the start position of the currently matched word (position in
	 *            the input word).
	 * @param wordPosition
	 *            the current position along the input word.
	 * @param node
	 *            the node we are currently at (i.e. the edge leading to this
	 *            node is relevant to us).
	 * @param maxErrors
	 *            the number of errors still allowed.
	 * @param currentNodeWordLength
	 *            the length of the word found along the nodes (this may be
	 *            different from the actual length due to buffer limits).
	 * @return the maximal length that can be taken.
	 */
	private int calculateMaxLength(int wordStart, int wordPosition, int node,
			int maxErrors, int currentNodeWordLength) {
		edBuffer[0][0] = 0;
		int currentLength = 1;
		for (; currentLength <= currentNodeWordLength; ++currentLength) {
			int best = currentLength;
			edBuffer[0][currentLength] = currentLength;
			edBuffer[currentLength][0] = currentLength;

			if (wordPosition + currentLength >= word.size()) {
				break;
			}

			// deal with case that character may not be matched (sentinel!)
			Object iChar = word.get(wordPosition + currentLength - 1);
			Object jChar = word.get(nodeWordBegin[node] + currentLength - 1);
			if (mayNotMatch(iChar) || mayNotMatch(jChar)) {
				break;
			}

			// usual matrix completion for edit distance
			for (int k = 1; k < currentLength; ++k) {
				best = Math.min(
						best,
						fillEDBuffer(k, currentLength, wordPosition,
								nodeWordBegin[node]));
			}
			for (int k = 1; k < currentLength; ++k) {
				best = Math.min(
						best,
						fillEDBuffer(currentLength, k, wordPosition,
								nodeWordBegin[node]));
			}
			best = Math.min(
					best,
					fillEDBuffer(currentLength, currentLength, wordPosition,
							nodeWordBegin[node]));

			if (best > maxErrors
					|| wordPosition - wordStart + currentLength <= headEquality
					&& best > 0) {
				break;
			}
		}
		--currentLength;
		return currentLength;
	}

	private void reportClone(int wordBegin, int wordEnd, int currentNode,
			int nodeWordPos, int nodeWordLength) throws ConQATException {
		int length = wordEnd - wordBegin;
		if (length < minLength || nodeWordLength < minLength) {
			return;
		}

		PairList<Integer, Integer> otherClones = new PairList<Integer, Integer>();
		findRemainingClones(otherClones, nodeWordLength, currentNode,
				nodeWordEnd[currentNode] - nodeWordBegin[currentNode]
				- nodeWordPos, wordBegin);

		int occurrences = 1 + otherClones.size();

		// check whether we may start from here
        PhpToken t = (PhpToken) word.get(wordBegin);
		CloneInfo newInfo = new CloneInfo(length, wordBegin, occurrences, t, otherClones);
		for (int index = Math.max(0, wordBegin - INDEX_SPREAD + 1); index <= wordBegin; ++index) {
			List<CloneInfo> existingClones = cloneInfos.getCollection(index);
			if (existingClones != null) {
				for (CloneInfo cloneInfo : existingClones) {
					if (cloneInfo.dominates(newInfo, wordBegin - index)) {
						// we already have a dominating clone, so ignore
						return;
					}
				}
			}
		}

		// report clone
		//consumer.startCloneClass(length);
		//consumer.addClone(wordBegin, length);
		//for (int i = wordBegin; i < length; i++) {
			//System.out.print(word.get(i) + " ");
		//}
        //PhpToken t = (PhpToken) word.get(wordBegin);
        //System.out.println("line = " + t.line + ", length = " + length);

		for (int clone = 0; clone < otherClones.size(); ++clone) {
			int start = otherClones.getFirst(clone);
			int otherLength = otherClones.getSecond(clone);
			//consumer.addClone(start, otherLength);
		}

		// is this clone actually relevant?
		//if (!consumer.completeCloneClass()) {
			//return;
		//}

		// add clone to otherClones to avoid getting more duplicates
		for (int i = wordBegin; i < wordEnd; i += INDEX_SPREAD) {
			cloneInfos.add(i, new CloneInfo(length - (i - wordBegin), wordBegin, occurrences, t, otherClones));
		}
        //PhpToken t = (PhpToken) word.get(wordBegin);
        //System.out.print("line = " + t.line + ", length = " + length + "; ");
		for (int clone = 0; clone < otherClones.size(); ++clone) {
			int start = otherClones.getFirst(clone);
			int otherLength = otherClones.getSecond(clone);
            //PhpToken s = (PhpToken) word.get(start);
            //System.out.print("start t.line = " + s.line + ", otherlength =  " + otherLength + " ");
            for (int j = 0; j < otherLength; j++) {
                PhpToken r = (PhpToken) word.get(j + start);
                //System.out.print(r.content + " " );
            }
			for (int i = 0; i < otherLength; i += INDEX_SPREAD) {
				cloneInfos.add(start + i, new CloneInfo(otherLength - i, wordBegin, occurrences, t, otherClones));
			}
		}
        //System.out.println("");
	}


	/**
	 * Fills the edit distance buffer at position (i,j).
	 * 
	 * @param i
	 *            the first index of the buffer.
	 * @param j
	 *            the second index of the buffer.
	 * @param iOffset
	 *            the offset where the word described by i starts.
	 * @param jOffset
	 *            the offset where the word described by j starts.
	 * @return the value inserted into the buffer.
	 */
	private int fillEDBuffer(int i, int j, int iOffset, int jOffset) {
		Object iChar = word.get(iOffset + i - 1);
		Object jChar = word.get(jOffset + j - 1);

		int insertDelete = 1 + Math.min(edBuffer[i - 1][j], edBuffer[i][j - 1]);
		int change = edBuffer[i - 1][j - 1] + (iChar.equals(jChar) ? 0 : 1);
		return edBuffer[i][j] = Math.min(insertDelete, change);
	}

	/**
	 * Fills a list of pairs giving the start positions and lengths of the
	 * remaining clones.
	 * 
	 * @param clonePositions the clone positions being filled (start position and length)
	 * @param nodeWordLength the length of the word along the nodes.
	 * @param currentNode the node we are currently at.
	 * @param distance the distance along the word leading to the current node.
	 * @param wordStart the start of the currently searched word.
	 */
	private void findRemainingClones(PairList<Integer, Integer> clonePositions,
			int nodeWordLength, int currentNode, int distance, int wordStart) {
		for (int nextNode = nodeChildFirst[currentNode]; nextNode >= 0; nextNode = nodeChildNext[nextNode]) {
			int node = nodeChildNode[nextNode];
			findRemainingClones(clonePositions, nodeWordLength, node, distance
					+ nodeWordEnd[node] - nodeWordBegin[node], wordStart);
		}

		if (nodeChildFirst[currentNode] < 0) {
			int start = word.size() - distance - nodeWordLength;
			if (start != wordStart) {
				clonePositions.add(start, nodeWordLength);
			}
		}
	}

	/**
	 * This should return true, if the provided character is not allowed to
	 * match with anything else (e.g. is a sentinel).
	 */
	protected abstract boolean mayNotMatch(Object character);

	/**
	 * This method is called whenever the {@link #MAX_LENGTH} is to small and
	 * hence the {@link #edBuffer} was not large enough. This may cause that a
	 * really large clone is reported in multiple chunks of size
	 * {@link #MAX_LENGTH} and potentially minor parts of such a clone might be
	 * lost.
	 */
	@SuppressWarnings("unused")
	protected void reportBufferShortage(int leafStart, int leafLength) {
		// empty base implementation
	}

	/** Stores information on a clone. */
	private static class CloneInfo {

		/** Length of the clone in tokens. */
		private final int length;

        /** Position in word list */
        public final int position;

		/** Number of occurrences of the clone. */
		private final int occurrences;

        public final PhpToken token;

        /** Related clones */
        public final PairList<Integer, Integer> otherClones;

		/** Constructor. */
		public CloneInfo(int length, int position, int occurrences, PhpToken token, PairList<Integer, Integer> otherClones) {
			this.length = length;
			this.position = position;
			this.occurrences = occurrences;
            this.token = token;
            this.otherClones = otherClones;
		}

		/**
		 * Returns whether this clone info dominates the given one, i.e. whether
		 * both {@link #length} and {@link #occurrences} s not smaller.
		 * 
		 * @param later
		 *            The amount the given clone starts later than the "this"
		 *            clone.
		 */
		public boolean dominates(CloneInfo ci, int later) {
			return length - later >= ci.length && occurrences >= ci.occurrences;
		}
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
