CREATE TABLE public.eventlog (
    type character varying(128),
    data text,
    sess text,
    gamets bigint NOT NULL,
    localts bigint NOT NULL,
    ts bigint,
    rowid bigint NOT NULL,
    people text,
    location text,
    party text,
    utterance_id text,
    delivery_state text
);
