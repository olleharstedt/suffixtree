class PhpToken {

    public int tokenCode;
    public int line;
    public String file;
    public String tokenName;
    public String content;

    public PhpToken(
        int tokenCode,
        String tokenName,
        int line,
        String file,
        String content
    ) {
        this.tokenCode = tokenCode;
        this.tokenName = tokenName;
        this.line = line;
        this.content = content;
        this.file = file;
    }

    /** {@inheritDoc} */
    @Override
    public int hashCode() {
        return content.hashCode();
        //return tokenCode;
    }

    /** {@inheritDoc} */
    @Override
    public boolean equals(Object token) {
        return ((PhpToken) token).hashCode() == this.hashCode();
    }

    /** {@inheritDoc} */
    @Override
    public String toString() {
        return tokenName;
    }
}
