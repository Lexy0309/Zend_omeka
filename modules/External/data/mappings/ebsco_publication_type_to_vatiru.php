<?php
/**
 * Map the ebsco publication types with the vatiru data (priority and main
 * publication type).
 *
 * Priorities are string, because the value in Omeka is a string, so the sort
 * uses the alphabetic order.
 *
 * This map can be changed according to priorities of Vatiru.
 * The precise type of the document is set in dcterms:format as rdf classes.
 */

return [
    ''                          => ['priority' => '999', 'type' => 'undefined'],
    'Academic Journal'          => ['priority' => '200', 'type' => 'article'],
    'Peer-Reviewed Journal'     => ['priority' => '200', 'type' => 'article'],
    'Periodical'                => ['priority' => '200', 'type' => 'article'],
    'Journal Article'           => ['priority' => '200', 'type' => 'article'],
    'Journal'                   => ['priority' => '200', 'type' => 'article'],
    'Trade Publication'         => ['priority' => '200', 'type' => 'article'],
    'Newspaper'                 => ['priority' => '200', 'type' => 'article'],
    'Newswire'                  => ['priority' => '200', 'type' => 'article'],
    'Book'                      => ['priority' => '100', 'type' => 'book'],
    'Almanac'                   => ['priority' => '100', 'type' => 'book'],
    'Reference Book'            => ['priority' => '100', 'type' => 'book'],
    'Book Collection'           => ['priority' => '100', 'type' => 'book'],
    'Essay'                     => ['priority' => '100', 'type' => 'book'],
    'Audiobook'                 => ['priority' => '100', 'type' => 'book'],
    'eBook'                     => ['priority' => '100', 'type' => 'book'],
    'Book Review'               => ['priority' => '200', 'type' => 'article'],
    'Film Review'               => ['priority' => '200', 'type' => 'article'],
    'Product Review'            => ['priority' => '200', 'type' => 'article'],
    'Country Report'            => ['priority' => '200', 'type' => 'article'],
    'Market Research Report'    => ['priority' => '200', 'type' => 'article'],
    'Report'                    => ['priority' => '200', 'type' => 'article'],
    'Educational Report'        => ['priority' => '200', 'type' => 'article'],
    'SWOT Analyse'              => ['priority' => '200', 'type' => 'article'],
    'Industry Report'           => ['priority' => '200', 'type' => 'article'],
    'Industry Profile'          => ['priority' => '200', 'type' => 'article'],
    'Grey Literature'           => ['priority' => '200', 'type' => 'article'],
    'Conference Paper'          => ['priority' => '200', 'type' => 'article'],
    'Conference Proceeding'     => ['priority' => '200', 'type' => 'article'],
    'Symposium'                 => ['priority' => '200', 'type' => 'article'],
    'Dissertation'              => ['priority' => '100', 'type' => 'book'],
    'These'                     => ['priority' => '100', 'type' => 'book'],
    'Biography'                 => ['priority' => '200', 'type' => 'article'],
    'Legal Document'            => ['priority' => '200', 'type' => 'article'],
    'Primary Source Document'   => ['priority' => '200', 'type' => 'article'],
    'Government Document'       => ['priority' => '200', 'type' => 'article'],
    'Music Score'               => ['priority' => '200', 'type' => 'article'],
    'Multiple Score Format'     => ['priority' => '200', 'type' => 'article'],
    'Voice Score'               => ['priority' => '200', 'type' => 'article'],
    'Printed Music'             => ['priority' => '200', 'type' => 'article'],
    'Research Starters Content' => ['priority' => '200', 'type' => 'article'],
    'Computer File'             => ['priority' => '999', 'type' => 'undefined'],
    'Computer'                  => ['priority' => '999', 'type' => 'undefined'],
    'Electronic Resource'       => ['priority' => '999', 'type' => 'undefined'],
    'Website'                   => ['priority' => '200', 'type' => 'article'],
    'CD-ROM'                    => ['priority' => '999', 'type' => 'undefined'],
    'Game'                      => ['priority' => '999', 'type' => 'undefined'],
    'Kit'                       => ['priority' => '999', 'type' => 'undefined'],
    'Mixed Material'            => ['priority' => '999', 'type' => 'undefined'],
    'Multimedia'                => ['priority' => '999', 'type' => 'undefined'],
    'Object'                    => ['priority' => '999', 'type' => 'undefined'],
    'Realia'                    => ['priority' => '999', 'type' => 'undefined'],
    'Visual Material'           => ['priority' => '999', 'type' => 'undefined'],
    'Technical Drawing'         => ['priority' => '999', 'type' => 'undefined'],
    'Audio'                     => ['priority' => '999', 'type' => 'undefined'],
    'Audiocassette'             => ['priority' => '999', 'type' => 'undefined'],
    'Audiovisual'               => ['priority' => '999', 'type' => 'undefined'],
    'Music'                     => ['priority' => '999', 'type' => 'undefined'],
    'Sound Recording'           => ['priority' => '999', 'type' => 'undefined'],
    'DVD'                       => ['priority' => '999', 'type' => 'undefined'],
    'Motion Picture'            => ['priority' => '999', 'type' => 'undefined'],
    'Video'                     => ['priority' => '999', 'type' => 'undefined'],
    'Map'                       => ['priority' => '999', 'type' => 'undefined'],
    // Others.
    'Serial'                    => ['priority' => '200', 'type' => 'article'],
    'News'                      => ['priority' => '200', 'type' => 'article'],
    'Interview'                 => ['priority' => '200', 'type' => 'article'],
    'Image'                     => ['priority' => '200', 'type' => 'article'],
    'Book Chapter'              => ['priority' => '100', 'type' => 'book'],
    'Handbook'                  => ['priority' => '100', 'type' => 'book'],
    'Reference'                 => ['priority' => '100', 'type' => 'book'],
    'Review'                    => ['priority' => '200', 'type' => 'article'],
    'Thesis'                    => ['priority' => '100', 'type' => 'book'],
    'Video Recording'           => ['priority' => '999', 'type' => 'undefined'],
    'Short Story'               => ['priority' => '999', 'type' => 'undefined'],
    'Literary Criticism'        => ['priority' => '200', 'type' => 'article'],
    'Poem'                      => ['priority' => '200', 'type' => 'article'],
    'Table '                    => ['priority' => '999', 'type' => 'undefined'],
    'Chart'                     => ['priority' => '999', 'type' => 'undefined'],
    'Patent'                    => ['priority' => '999', 'type' => 'undefined'],
    'Annual Report'             => ['priority' => '200', 'type' => 'article'],
    'Case Study'                => ['priority' => '200', 'type' => 'article'],
    'Health Report'             => ['priority' => '200', 'type' => 'article'],
    'Working Papers'            => ['priority' => '200', 'type' => 'article'],
    'Encyclopedia'              => ['priority' => '100', 'type' => 'book'],
    'Dictionary'                => ['priority' => '100', 'type' => 'book'],
    'Yearbook'                  => ['priority' => '100', 'type' => 'book'],
    'Glossary'                  => ['priority' => '100', 'type' => 'book'],
    'Congressional Document'    => ['priority' => '200', 'type' => 'article'],
    'Microform'                 => ['priority' => '999', 'type' => 'undefined'],
    'Conference'                => ['priority' => '200', 'type' => 'article'],
    'Conference Document'       => ['priority' => '200', 'type' => 'article'],
    'Meeting Paper Abstract'    => ['priority' => '200', 'type' => 'article'],
    'Proceeding'                => ['priority' => '200', 'type' => 'article'],
    'Editorial'                 => ['priority' => '200', 'type' => 'article'],
    'Opinion paper'             => ['priority' => '200', 'type' => 'article'],
    'Commentary'                => ['priority' => '200', 'type' => 'article'],
    'Review'                    => ['priority' => '200', 'type' => 'article'],
    'Sound Recording Review'    => ['priority' => '200', 'type' => 'article'],
    'Musical Comedy Review'     => ['priority' => '200', 'type' => 'article'],
    'Performance Review'        => ['priority' => '200', 'type' => 'article'],
    'Entertainment Review'      => ['priority' => '200', 'type' => 'article'],
    'Product Evaluations'       => ['priority' => '200', 'type' => 'article'],
    'Radio Program Review'      => ['priority' => '200', 'type' => 'article'],
    'Systematic Review'         => ['priority' => '200', 'type' => 'article'],
    'Theater Review'            => ['priority' => '200', 'type' => 'article'],
    'Transcript'                => ['priority' => '200', 'type' => 'article'],
    'Autobiography'             => ['priority' => '200', 'type' => 'article'],
    'Electronic  Book'          => ['priority' => '100', 'type' => 'book'],
    'Electronic Book'           => ['priority' => '100', 'type' => 'book'],
    // Added after online checks.
    'Primary Source'            => ['priority' => '200', 'type' => 'article'],
];
