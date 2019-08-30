<?php
/**
 * The ebsco publication types mix support, format, source and subject.
 * Only type is kept here.
 * The ebsco pub type is kept besides.
 *
 * @todo The ebsco mapping should be checked and completed with a subject mapping for some publication type.
 */

return [
    ''                          => [],
    'Academic Journal'          => ['dctype:Text', 'bibo:Journal', 'bibo:AcademicArticle'],
    'Peer-Reviewed Journal'     => ['dctype:Text', 'bibo:Journal', 'bibo:AcademicArticle'],
    'Periodical'                => ['dctype:Text', 'bibo:Periodical', 'bibo:Article'],
    'Journal Article'           => ['dctype:Text', 'bibo:AcademicArticle'],
    'Journal'                   => ['dctype:Text', 'bibo:Journal', 'bibo:AcademicArticle'],
    'Trade Publication'         => ['dctype:Text', 'bibo:Periodical', 'bibo:Article'],
    'Newspaper'                 => ['dctype:Text', 'bibo:Newspaper', 'bibo:Article'],
    'Newswire'                  => ['dctype:Text', 'bibo:Article'],
    'Book'                      => ['dctype:Text', 'bibo:Book'],
    'Almanac'                   => ['dctype:Text', 'bibo:ReferenceSource'],
    'Reference Book'            => ['dctype:Text', 'bibo:Book', 'bibo:ReferenceSource'],
    'Book Collection'           => ['dctype:Text', 'bibo:Book', 'bibo:Collection'],
    'Essay'                     => ['dctype:Text', 'bibo:Book'],
    'Audiobook'                 => ['dctype:Text', 'dctype:Sound', 'bibo:Book', 'bibo:AudioDocument'],
    'eBook'                     => ['dctype:Text', 'bibo:Book'],
    'Book Review'               => ['dctype:Text', 'bibo:AcademicArticle', 'bibo:Article'],
    'Film Review'               => ['dctype:Text', 'bibo:AcademicArticle', 'bibo:Article'],
    'Product Review'            => ['dctype:Text', 'bibo:AcademicArticle', 'bibo:Article'],
    'Country Report'            => ['dctype:Text', 'bibo:Report'],
    'Market Research Report'    => ['dctype:Text', 'bibo:Report'],
    'Report'                    => ['dctype:Text', 'bibo:Report'],
    'Educational Report'        => ['dctype:Text', 'bibo:Report'],
    'SWOT Analyse'              => ['dctype:Text', 'bibo:Report'],
    'Industry Report'           => ['dctype:Text', 'bibo:Report'],
    'Industry Profile'          => ['dctype:Text', 'bibo:Report'],
    'Grey Literature'           => ['dctype:Text', 'bibo:Report'],
    'Conference Paper'          => ['dctype:Text', 'bibo:Proceedings', 'bibo:AcademicArticle'],
    'Conference Proceeding'     => ['dctype:Text', 'bibo:Proceedings', 'bibo:AcademicArticle'],
    'Symposium'                 => ['dctype:Text', 'bibo:Conference'],
    'Dissertation'              => ['dctype:Text', 'bibo:Thesis'],
    'These'                     => ['dctype:Text', 'bibo:Thesis'],
    'Biography'                 => ['dctype:Text'],
    'Legal Document'            => ['dctype:Text', 'bibo:LegalDocument'],
    'Primary Source Document'   => ['dctype:Text', 'bibo:Manuscript'],
    'Government Document'       => ['dctype:Text'],
    'Music Score'               => ['dctype:Text'],
    'Multiple Score Format'     => ['dctype:Text'],
    'Voice Score'               => ['dctype:Text'],
    'Printed Music'             => ['dctype:Text'],
    'Research Starters Content' => ['dctype:Text'],
    'Computer File'             => [],
    'Computer'                  => ['dctype:PhysicalObject'],
    'Electronic Resource'       => [],
    'Website'                   => ['dctype:Text', 'bibo:Website'],
    'CD-ROM'                    => ['dctype:PhysicalObject'],
    'Game'                      => ['dctype:InteractiveResource'],
    'Kit'                       => ['dctype:PhysicalObject'],
    'Mixed Material'            => ['dctype:PhysicalObject'],
    'Multimedia'                => ['dctype:MovingImage', 'dctype:Sound', 'bibo:AudioVisualDocument'],
    'Object'                    => ['dctype:PhysicalObject'],
    'Realia'                    => [],
    'Visual Material'           => ['dctype:PhysicalObject', 'dctype:StillImage', 'dctype:MovingImage', 'bibo:Image', 'bibo:Film'],
    'Technical Drawing'         => ['dctype:StillImage', 'bibo:Image'],
    'Audio'                     => ['dctype:Sound', 'bibo:AudioDocument'],
    'Audiocassette'             => ['dctype:PhysicalObject', 'dctype:Sound', 'bibo:AudioDocument'],
    'Audiovisual'               => ['dctype:MovingImage', 'dctype:Sound', 'bibo:AudioVisualDocument'],
    'Music'                     => ['dctype:Sound', 'bibo:AudioDocument'],
    'Sound Recording'           => ['dctype:Sound', 'bibo:AudioDocument'],
    'DVD'                       => ['dctype:PhysicalObject'],
    'Motion Picture'            => ['dctype:StillImage', 'dctype:MovingImage'],
    'Video'                     => ['dctype:MovingImage', 'bibo:Film'],
    'Map'                       => ['bibo:Map'],
    // Others.
    'Serial'                    => ['dctype:Text', 'bibo:Periodical', 'bibo:Article'],
    'News'                      => ['dctype:Text', 'bibo:Newspaper', 'bibo:Article'],
    'Interview'                 => ['dctype:Text', 'bibo:Interview'],
    'Image'                     => ['dctype:StillImage', 'bibo:Image'],
    'Book Chapter'              => ['dctype:Text', 'bibo:Chapter'],
    'Handbook'                  => ['dctype:Text', 'bibo:Book'],
    'Reference'                 => ['dctype:Text'],
    'Review'                    => ['dctype:Text', 'bibo:AcademicArticle', 'bibo:Article'],
    'Thesis'                    => ['dctype:Text', 'bibo:Thesis'],
    'Video Recording'           => ['dctype:MovingImage', 'bibo:Film'],
    'Short Story'               => ['dctype:Text'],
    'Literary Criticism'        => ['dctype:Text', 'bibo:AcademicArticle'],
    'Poem'                      => ['dctype:Text'],
    'Table '                    => ['dctype:Text', 'dctype:StillImage'],
    'Chart'                     => ['dctype:Text', 'dctype:StillImage'],
    'Patent'                    => ['dctype:Text', 'bibo:Patent'],
    'Annual Report'             => ['dctype:Text', 'bibo:Report'],
    'Case Study'                => ['dctype:Text', 'bibo:Report'],
    'Health Report'             => ['dctype:Text', 'bibo:Report'],
    'Working Papers'            => ['dctype:Text', 'bibo:Report'],
    'Encyclopedia'              => ['dctype:Text', 'bibo:ReferenceSource'],
    'Dictionary'                => ['dctype:Text', 'bibo:ReferenceSource'],
    'Yearbook'                  => ['dctype:Text', 'bibo:ReferenceSource'],
    'Glossary'                  => ['dctype:Text', 'bibo:ReferenceSource'],
    'Congressional Document'    => ['dctype:Text'],
    'Microform'                 => ['dctype:PhysicalObject', 'dctype:Text'],
    'Conference'                => ['dctype:Text', 'bibo:Proceedings', 'bibo:AcademicArticle'],
    'Conference Document'       => ['dctype:Text', 'bibo:Proceedings', 'bibo:AcademicArticle'],
    'Meeting Paper Abstract'    => ['dctype:Text', 'bibo:Proceedings', 'bibo:AcademicArticle'],
    'Proceeding'                => ['dctype:Text', 'bibo:Proceedings', 'bibo:AcademicArticle'],
    'Editorial'                 => ['dctype:Text', 'bibo:AcademicArticle', 'bibo:Article'],
    'Opinion paper'             => ['dctype:Text', 'bibo:AcademicArticle', 'bibo:Article'],
    'Commentary'                => ['dctype:Text', 'bibo:AcademicArticle', 'bibo:Article'],
    'Review'                    => ['dctype:Text', 'bibo:AcademicArticle', 'bibo:Article'],
    'Sound Recording Review'    => ['dctype:Text', 'bibo:AcademicArticle', 'bibo:Article'],
    'Musical Comedy Review'     => ['dctype:Text', 'bibo:AcademicArticle', 'bibo:Article'],
    'Performance Review'        => ['dctype:Text', 'bibo:AcademicArticle', 'bibo:Article'],
    'Entertainment Review'      => ['dctype:Text', 'bibo:AcademicArticle', 'bibo:Article'],
    'Product Evaluations'       => ['dctype:Text', 'bibo:AcademicArticle', 'bibo:Article'],
    'Radio Program Review'      => ['dctype:Text', 'bibo:AcademicArticle', 'bibo:Article'],
    'Systematic Review'         => ['dctype:Text', 'bibo:AcademicArticle', 'bibo:Article'],
    'Theater Review'            => ['dctype:Text', 'bibo:AcademicArticle', 'bibo:Article'],
    'Transcript'                => ['dctype:Text'],
    'Autobiography'             => ['dctype:Text'],
    'Electronic  Book'          => ['dctype:Text', 'bibo:Book'],
    'Electronic Book'           => ['dctype:Text', 'bibo:Book'],
    // Added after online checks.
    'Primary Source'            => ['dctype:Text', 'bibo:Manuscript'],
];

/*
$dctermsClasses = <<<LIST
dcterms:Agent
dcterms:AgentClass
dcterms:BibliographicResource
dcterms:FileFormat
dcterms:Frequency
dcterms:Jurisdiction
dcterms:LicenseDocument
dcterms:LinguisticSystem
dcterms:Location
dcterms:LocationPeriodOrJurisdiction
dcterms:MediaType
dcterms:MediaTypeOrExtent
dcterms:MethodOfAccrual
dcterms:MethodOfInstruction
dcterms:PeriodOfTime
dcterms:PhysicalMedium
dcterms:PhysicalResource
dcterms:Policy
dcterms:ProvenanceStatement
dcterms:RightsStatement
dcterms:SizeOrDuration
dcterms:Standard
LIST;

$dctypeClasses = <<<LIST
dctype:Collection
dctype:Dataset
dctype:Event
dctype:Image
dctype:InteractiveResource
dctype:MovingImage
dctype:PhysicalObject
dctype:Service
dctype:Software
dctype:Sound
dctype:StillImage
dctype:Text
LIST;

$biboClasses = <<<LIST
bibo:AcademicArticle
bibo:Article
bibo:AudioDocument
bibo:AudioVisualDocument
bibo:Bill
bibo:Book
bibo:BookSection
bibo:Brief
bibo:Chapter
bibo:Code
bibo:CollectedDocument
bibo:Collection
bibo:Conference
bibo:CourtReporter
bibo:LegalDecision
bibo:Document
bibo:DocumentPart
bibo:DocumentStatus
bibo:EditedBook
bibo:Email
bibo:Event
bibo:Excerpt
bibo:Film
bibo:Hearing
bibo:Image
bibo:Interview
bibo:Issue
bibo:Journal
bibo:LegalCaseDocument
bibo:LegalDocument
bibo:Legislation
bibo:Letter
bibo:Magazine
bibo:Manual
bibo:Manuscript
bibo:Map
bibo:Newspaper
bibo:Note
bibo:Patent
bibo:Performance
bibo:Periodical
bibo:PersonalCommunication
bibo:PersonalCommunicationDocument
bibo:Proceedings
bibo:Quote
bibo:ReferenceSource
bibo:Report
bibo:MultiVolumeBook
bibo:Series
bibo:Slide
bibo:Slideshow
bibo:Standard
bibo:Statute
bibo:Thesis
bibo:Webpage
bibo:Website
bibo:Workshop
LIST;

*/

/*
// Mapping from PubType to PubTypeId.
$mapPubType = [
    '' => '', // "unknown" or "unassigned"
    'Academic Journal' => 'academicJournal',
    'Peer-Reviewed Journal' => '',
    'Periodical' => 'serialPeriodical',
    'Journal Article' => 'academicJournal',
    'Journal' => '',
    'Trade Publication' => 'serialPeriodical',
    'Newspaper' => 'newspaperArticle',
    'Newswire' => 'newspaperArticle',
    'Book' => 'book',
    'Almanac' => 'reference',
    'Reference Book' => '',
    'Book Collection' => '',
    'Essay' => 'serialPeriodical',
    'Audiobook' => '',
    'eBook' => 'ebook',
    'Book Review' => 'academicJournal', // review
    'Film Review' => '',
    'Product Review' => '',
    'Country Report' => 'report',
    'Market Research Report' => 'report',
    'Report' => 'report',
    'Educational Report' => 'report', // empty
    'SWOT Analyse' => 'report', // empty
    'Industry Report' => 'report', // empty
    'Industry Profile' => '', // unknown
    'Grey Literature' => 'report',
    'Conference Paper' => 'conference',
    'Conference Proceeding' => 'conference',
    'Symposium' => '',
    'Dissertation' => 'dissertation',
    'These' => '',
    'Biography' => 'biography',
    'Legal Document' => '',
    'Primary Source Document' => 'primarySource', // PubType is "Primary Source" but search requires "Primary Source Document".
    'Government Document' => 'governmentDocument',
    // 'Music' => '', // empty
    'Music Score' => 'score',
    'Multiple Score Format' => '',
    'Voice Score' => '',
    'Printed Music' => '',
    'Research Starters Content' => '',
    'Computer File' => 'electronicResource',
    'Computer' => '',
    'Electronic Resource' => 'electronicResource', // Note: some other type are available as electronic resource (book…).
    // 'eBook' => 'ebook',
    'Website' => '',
    'CD-ROM' => '',
    'Game' => '',
    'Kit' => '',
    'Mixed Material' => '',
    'Multimedia' => '',
    'Object' => '',
    'Realia' => '',
    'Visual Material' => '',
    'Technical Drawing' => '',
    'Audio' => 'audio',
    // 'Audiobook' => '',
    'Audiocassette' => '',
    'Audiovisual' => '',
    'Music' => '',
    'Sound Recording' => 'audio',
    'DVD' => '',
    'Motion Picture' => '',
    'Video' => 'videoRecording',
    // 'eBook' => '',
    'Map' => 'map',
    // Others.
    'Serial' => 'serialPeriodical',
    'News' => 'newspaperArticle',
    'Interview' => 'newspaperArticle', // But results are "academicArticle".
    'Image' => 'image',
    'Book Chapter' => 'book',
    'Handbook' => 'book', // empty
    'Reference' => 'reference',
    'Review' => 'review',
    'Thesis' => 'dissertation',
    'Video Recording' => 'videoRecording',
    'Short Story' => '', // empty
    'Literary Criticism' => '',  // empty
    'Poem' => '', // empty
    'Table ' => '', // empty
    'Chart' => '', // unknown
    'Patent' => 'patent',
    'Report' => 'report',
    'Annual Report' => 'report',
    'Country Report' => 'report',
    'Case Study' => 'report',
    'Grey Literature' => 'report',
    'Health Report' => 'report',
    'Market Research Report' => 'report',
    'Working Papers' => 'report',
    'Encyclopedia' => 'reference',
    'Dictionary' => 'reference',
    'Almanac' => 'reference',
    'Yearbook' => 'reference',
    'Glossary' => 'reference',
    'Congressional Document' => '',
    'Government Document' => 'governmentDocument',
    'Primary Source Document' => 'primarySource',
    'Electronic Resource' => 'electronicResource',
    'Multimedia' => '', // empty
    'Microform' => 'book',
    'Conference Paper' => 'conference',
    'Conference Document' => 'conference',
    'Conference Proceeding' => 'conference',
    'Meeting Paper Abstract' => '',
    'Proceeding' => '',
    'Symposium' => '',
    'Editorial' => 'editorialOpinion', // "Editorial & Opinion" (or academic paper)
    'Opinion paper' => '',
    'Commentary' => '',
    'Review' => '',
    'Book Review' => '',
    'Product Review' => '',
    'Sound Recording Review' => '',
    'Musical Comedy Review' => '',
    'Performance Review' => '',
    'Entertainment Review' => '',
    'Product Evaluations' => '',
    'Radio Program Review' => '',
    'Systematic Review' => '',
    'Theater Review' => '',
    'Transcript' => 'transcript',
    'Biography' => 'biography',
    'Autobiography' => '',
    'eBook' => 'ebook',
    'Electronic  Book' => 'ebook',
    'Audiobook' => '',
];
*/
