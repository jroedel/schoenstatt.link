#submitted ON 2017-05-12
ALTER TABLE sch_publications ADD HasNoISBN BIT DEFAULT 0 NOT NULL ;
ALTER TABLE sch_publications ADD IsRevisedWithBookInHand BIT DEFAULT 0 NOT NULL ;
ALTER TABLE sch_publications ADD PublishDataAsJsonLd BIT DEFAULT 0 NOT NULL ;
ALTER TABLE sch_publications ADD IsFormallyPublished BIT DEFAULT 0 NOT NULL ;


ALTER TABLE sch_publications ADD TranslatedFromPublicationId INT DEFAULT NULL NULL ;

ALTER TABLE sch_publications MODIFY COLUMN HasNoISBN BOOL DEFAULT b'0' NOT NULL ;
ALTER TABLE sch_publications MODIFY COLUMN IsRevisedWithBookInHand BOOL DEFAULT b'0' NOT NULL ;
ALTER TABLE sch_publications MODIFY COLUMN PublishDataAsJsonLd BOOL DEFAULT b'0' NOT NULL ;
ALTER TABLE sch_publications MODIFY COLUMN IsFormallyPublished BOOL DEFAULT b'0' NOT NULL ;