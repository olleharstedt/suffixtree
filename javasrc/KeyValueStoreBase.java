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

import java.util.HashMap;
import java.util.Map;

/**
 * Base class that implements a lazily instantiated associative key-value
 * mechanism. Lazy instantiation saves memory in case the store is not used.
 * 
 * @author $Author: hummelb $
 * @version $Rev: 35940 $
 * @ConQAT.Rating GREEN Hash: AD653342941409AEA04D6C1670520DFE
 */
public abstract class KeyValueStoreBase {

	/**
	 * Integer that identifies this value store. The scope of the id depends on
	 * the id provider used.
	 */
	private final long id;

	/** Map that stores key-value pairs */
	private Map<String, Object> values;

	/** Map that stores transient flags */
	private Map<String, Boolean> transientFlags;

	/** Construct store with set id */
	protected KeyValueStoreBase(long id) {
		this.id = id;
	}

	/** Return the id of the store */
	public long getId() {
		return id;
	}

	/** Stores a value in the {@link CloneClass} by using a keyword. */
	public void setValue(String key, Object value) {
		ensureValuesMapInitialized();
		values.put(key, value);
	}

	/** Gets a value */
	public Object getValue(String key) {
		if (values == null) {
			return null;
		}
		return values.get(key);
	}

	/** Checks whether a value is stored under this key */
	public boolean containsValue(String key) {
		if (values == null) {
			return false;
		}
		return values.containsKey(key);
	}

	/**
	 * Get a sorted list of the keys stored. Each key is guaranteed to only
	 * appear once. Keys are sorted in lexical order.
	 */
	public UnmodifiableList<String> getKeyList() {
		if (values == null) {
			return CollectionUtils.emptyList();
		}
		return CollectionUtils.asSortedUnmodifiableList(values.keySet());
	}

	/** Gets an int value */
	public int getInt(String key) {
		return (Integer) getValue(key);
	}

	/** Return stored long */
	public long getLong(String key) {
		return (Long) getValue(key);
	}
	
	/** Return stored long */
	public double getDouble(String key) {
		return (Double) getValue(key);
	}

	/** Gets a string value */
	public String getString(String key) {
		return (String) getValue(key);
	}

	/** Ensures that the values map is initialized */
	private void ensureValuesMapInitialized() {
		if (values == null) {
			values = new HashMap<String, Object>();
		}
	}

	/** Ensures that the values map is initialized */
	private void ensureTransientFlagsMapInitialized() {
		if (transientFlags == null) {
			transientFlags = new HashMap<String, Boolean>();
		}
	}

	/** Determines whether a value is transient. Default value is false. */
	public boolean getTransient(String key) {
		if (transientFlags == null || !transientFlags.containsKey(key)) {
			return false;
		}
		return transientFlags.get(key);
	}

	/** Stores the transient flag for a key */
	public void setTransient(String key, boolean value) {
		ensureTransientFlagsMapInitialized();
		transientFlags.put(key, value);
	}
}
