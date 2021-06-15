import java.util.ArrayList;
import java.util.List;
import java.util.Random;

class SuffixTreeTest {

	public static void main(String[] args) {
        System.err.println("hello");
		String s = "Test basic behaviour of the suffix tree Test basic behaviour of the suffix Creates a list of its characters for a given string tree abcabcabc";
		//@SuppressWarnings({ "unchecked", "rawtypes" })
		List<Object> word = (List) stringToList(s);
		word.add(new SuffixTree.Sentinel());
		SuffixTree stree = new SuffixTree(word);

		for (int i = 0; i < s.length(); ++i) {
			for (int j = i + 1; j <= s.length(); ++j) {
				String substr = s.substring(i, j);
				if (stree.containsWord(stringToList(substr))) {
                    System.err.println("hello");
                }
			}
		}

		for (String test : new String[] { "abd", "xyz", s + "a" }) {
			//assertFalse("Should not contain " + test, stree.containsWord(this.stringToList(test)));
		}
	}

	/** Creates a list of its characters for a given string. */
	public static List<Character> stringToList(String s) {
		List<Character> result = new ArrayList<Character>();
		for (int i = 0; i < s.length(); ++i) {
			result.add(s.charAt(i));
		}
		return result;
	}
}
