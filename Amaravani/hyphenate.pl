#!/usr/bin/perl

use List::MoreUtils qw(uniq);

$listOfFiles = $ARGV[0];
$dictionaryFile = $ARGV[1];

open(FILES,"<:utf8",$listOfFiles) or die "can't open $listOfFiles\n";

$file = <FILES>;

@lines = ();

while($file)
{	
	chop($file);
	
	$fileName = $file;
	open(IN,"<:utf8",$fileName) or die "can't open $fileName\n";
	
	@flines = <IN>;
	
	@lines = (@lines,@flines);
	
	$file = <FILES>;
	close(IN);	
}

$fileOut = $dictionaryFile;

#~ print $fileName;

$vyanjana = 'k|K|g|G|kn|c|C|j|J|cn|T|Th|D|Dh|N|t|th|d|dh|n|p|P|b|B|m|y|r|l|v|sh|S|s|h|L|Q';
$swara = 'a|A|i|I|u|U|e|E|ai|o|O|au|M|H|V|Y|q|w|x|X|f|R';
$special = 'x|X';
$syllable = "($vyanjana)($swara)($swara)($swara)|($vyanjana)($swara)($swara)|($vyanjana)($swara)|($swara)($swara)|($swara)";
$specialCases = "($syllable)($special)|($vyanjana)($special)";

open(OUT,">:utf8",$fileOut) or die "can't open $fileOut\n";



#~ print @lines;

$content = join(' ', @lines);

$content =~ s/%~.*\n//g;
$content =~ s/\n/ /g;

$content =~ s/\\\\/\n\\\n/g;
$content =~ s/\\-//g;
$content =~ s/\\s/\n\\s\n/g;
$content =~ s/\\/\n\\/g;
$content =~ s/(\\[a-zA-Z]+)/\1\n/g;
$content =~ s/\$/\n\$\n/g;

$content =~ s/\\[,;~!]/ /g;
$content =~ s/\}/}\n/g;
$content =~ s/\{/{\n/g;
$content =~ s/\\.*\n//g;

$content =~ s/[[:punct:]]/ /g;
$content =~ s/\d+/ /g;
$content =~ s/\s+/ /g;

@words = split(' ',$content);

@words = uniq @words;
@words = sort @words;

print OUT "\\hyphenation{\n";

for($i=0;$i<@words;$i++)
{
	#~ print $words[$i]."\n";
	
	$tmp = $words[$i];
	$tmp =~ s/($syllable)/\1-/g;
	$tmp =~ s/-($specialCases)/\1/g;
	$tmp =~ s/-$//;
	$tmp =~ s/^(.*?)-(.*)$/\1\2/;
	$tmp =~ s/^(.*)-(.*)$/\1\2/;
	
	
	#~ print $words[$i] . ' -> ' . $tmp . "\n";
	print OUT $tmp . "\n";
}

print OUT "}\n";

close(OUT);
