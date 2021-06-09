class PhpToken {

    protected int tokenCode;
    protected int line;
    protected String tokenName;
    protected String content;

    public PhpToken(
        int tokenCode,
        String tokenName,
        int line,
        String content
    ) {
        this.tokenCode = tokenCode;
        this.tokenName = tokenName;
        this.line = line;
        this.content = content;
    }

    /** {@inheritDoc} */
    @Override
    public int hashCode() {
        return tokenCode;
    }

    /** {@inheritDoc} */
    @Override
    public boolean equals(Object token) {
        return ((PhpToken) token).tokenCode == tokenCode;
    }

    /** {@inheritDoc} */
    @Override
    public String toString() {
        return tokenName;
    }
}
