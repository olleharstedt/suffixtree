class Sentinel extends PhpToken {

    /** The hash value used. */
    private final int hash = (int) (Math.random() * Integer.MAX_VALUE);

    public Sentinel(
        int tokenCode,
        String tokenName,
        int line,
        String file,
        String content
    ) {
        super(tokenCode, tokenName, line, file, content);

        this.tokenCode = 0;
        this.tokenName = "sentinel";
        this.line = 0;
        this.content = "sentinel";
        this.file = "no_file";
    }

    /** {@inheritDoc} */
    @Override
    public int hashCode() {
        return hash;
    }

    /** {@inheritDoc} */
    @Override
    public boolean equals(Object obj) {
        return obj == this;
    }

    /** {@inheritDoc} */
    @Override
    public String toString() {
        return "$";
    }
}
