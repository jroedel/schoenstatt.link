ALTER TABLE sch_publications ADD HasNoISBN BIT DEFAULT 0 NOT NULL ;
ALTER TABLE sch_publications ADD IsRevisedWithBookInHand BIT DEFAULT 0 NOT NULL ;
ALTER TABLE sch_publications ADD PublishDataAsJsonLd BIT DEFAULT 0 NOT NULL ;
ALTER TABLE sch_publications ADD IsFormallyPublished BIT DEFAULT 0 NOT NULL ;
