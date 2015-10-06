# parse_bychar
State machine approach to parsing well-formed XML (and adding missing bits along the way)

The SGML standard has an appendix describing the distinction between structure-controlled applications and markup-sensitive applications. The principles apply for XML as well as SGML applications. The key chapter is available as a public recommendation for a revision; the particular material is under the heading, [Attachment 1: The ISO 8879 Element Structure Information Set (ESIS)](http://www.sgmlsource.com/8879/n1035.htm). This attachment does not describe a syntax for this information set; there is a useable one created by James Clark for his original NSGMLS parser: [NSGMLS: An SGML System Conforming to International Standard ISO 8879](http://www.jclark.com/sp/sgmlsout.htm).

This web application is a PHP parser that uses a finite state machine approach to parsing a document by character and creating state transitions that represent ESIS events; these are output in a manner similar to that of James Clark's NSGMLS parser. Well formed content always produces a popped element stack of 0\. With appropriate input rules that define empty elements, this parser can effectively turn non-well-formed content into a Well Formed result stream that can then be used as "Source Tree" input to an XSLT transformation to other result formats (such as DITA, which was the impetus for my writing this parser).

**Notes:**

1.  This is not necessarily a fully conforming parser for even well-formed XML. Notably, end-of-line handling still needs to be implemented, as do many other type-checking events during the parse (eg, detecting attribute values in the absense of quote marks).
2.  If the doctype represents XHTML, the document is expected to be well-formed XML, otherwise a language rule is invoked to generate closing markup events for normally empty start tags with no closing delimiter.
3.  If the attribute value has no LIT or LITA delimiters, a special mode uses ' ' or '>' to complete the value scope.
4.  If markup ends are impliable if known per language type, stop conditions can be used as "end tag" events.
5.  If the markup is HTML, the parser normalizes the element case to lowercase to ensure more consistent closures during the parse.
6.  If invoked with no parameter, a default topic ("Dictation Task.html") will be parsed.
7.  Use the ?infile= query parameter at the end of this URl to pass the URL of an HTML target for parsing. For example, try some of these:
    *   <tt>[?infile=http://core.jumpchart.com/help/article/21/](http://learningbywrote.com/demo/read_bychar.php?infile=http://core.jumpchart.com/help/article/21/)</tt>
    *   <tt>[?infile=http://www.sgmlsource.com/8879/n1035.htm](http://learningbywrote.com/demo/read_bychar.php?infile=http://www.sgmlsource.com/8879/n1035.htm)</tt> (has known unclosed definition terms and other atrocious HTML in it)
    *   <tt>[?infile=http://www.jclark.com/sp/sgmlsout.htm](http://learningbywrote.com/demo/read_bychar.php?infile=http://www.jclark.com/sp/sgmlsout.htm)</tt> (also has unclosed definition terms)
    *   <tt>[?infile=](http://learningbywrote.com/demo/read_bychar.php?infile=)</tt>
