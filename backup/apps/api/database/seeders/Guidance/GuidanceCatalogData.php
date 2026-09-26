<?php

declare(strict_types=1);

namespace Database\Seeders\Guidance;

use App\Domains\Guidance\Enums\WorkflowDestinationAction;

/**
 * The curated launch catalog content.
 *
 * Curation transparency is a product invariant here, not decoration. Every tool
 * entry carries an honest provenance, cost note, privacy note, and limitation.
 * Entries describe real, widely used products; where current pricing or data
 * handling could not be confirmed at review time the limitation says so instead
 * of guessing. Listing a product is never an endorsement, and EduConnect has no
 * commercial relationship with any vendor named here.
 *
 * Academic integrity framing is preserved throughout: nothing in this catalog
 * exists to produce work a student then submits as their own.
 */
final class GuidanceCatalogData
{
    /** Provenance for entries summarised from a commercial vendor's own docs. */
    private const VENDOR_DOCS = 'Summarised from the vendor public product and privacy documentation and reviewed by the EduConnect learning team. Listing is not an endorsement and EduConnect has no commercial relationship with the vendor.';

    /** Provenance for open-source projects with public repositories. */
    private const OPEN_SOURCE = 'Summarised from the project public documentation and source repository and reviewed by the EduConnect learning team. Listing is not an endorsement.';

    /** Provenance for tools published by universities or non-profits. */
    private const INSTITUTION = 'Summarised from the publishing institution or non-profit public documentation and reviewed by the EduConnect learning team. Listing is not an endorsement.';

    /** Provenance for material the EduConnect learning team wrote itself. */
    private const IN_HOUSE = 'Drafted and reviewed by the EduConnect learning team.';

    private const PRICING_CAVEAT = ' Pricing and plan boundaries change often, so confirm the current plan on the vendor site before you rely on it.';

    private const PRIVACY_CAVEAT = ' Read the current privacy policy yourself and check your institution policy before uploading unpublished or confidential coursework.';

    /**
     * @return list<array{slug: string, name: string, description: string, sort_order: int}>
     */
    public static function categories(): array
    {
        return [
            ['slug' => 'study-planning', 'name' => 'Study planning', 'description' => 'Plan, timebox, and review study sessions so effort lands where it matters.', 'sort_order' => 10],
            ['slug' => 'academic-writing', 'name' => 'Academic writing', 'description' => 'Draft, structure, and revise written work that stays in your own voice.', 'sort_order' => 20],
            ['slug' => 'research-and-sources', 'name' => 'Research and sources', 'description' => 'Find, screen, and trace credible sources for an assignment or dissertation.', 'sort_order' => 30],
            ['slug' => 'citation-and-referencing', 'name' => 'Citation and referencing', 'description' => 'Collect references and keep citations consistent and verifiable.', 'sort_order' => 40],
            ['slug' => 'exam-preparation', 'name' => 'Exam preparation', 'description' => 'Retrieval practice, spaced repetition, and past-paper drilling.', 'sort_order' => 50],
            ['slug' => 'note-taking', 'name' => 'Note taking', 'description' => 'Capture lectures and reading in a form you can actually revise from.', 'sort_order' => 60],
            ['slug' => 'math-and-quantitative', 'name' => 'Maths and quantitative work', 'description' => 'Check working, visualise functions, and build quantitative intuition.', 'sort_order' => 70],
            ['slug' => 'coding-and-cs', 'name' => 'Coding and computer science', 'description' => 'Write, run, and debug code for coursework and practice.', 'sort_order' => 80],
            ['slug' => 'presentations-and-visuals', 'name' => 'Presentations and visuals', 'description' => 'Build slides, diagrams, and figures that carry an argument clearly.', 'sort_order' => 90],
            ['slug' => 'accessibility-and-inclusion', 'name' => 'Accessibility and inclusion', 'description' => 'Read, listen, caption, and check work so material is usable by everyone.', 'sort_order' => 100],
        ];
    }

    /**
     * @return list<array{category: string, name: string, purpose: string, selection_reason: string, use_cases: list<string>, usage_guidance: string, limitations: string, cost_note: string, privacy_note: string, external_url: string, provenance: string}>
     */
    public static function tools(): array
    {
        return [
            // ---------------------------------------------------------------
            // Study planning
            // ---------------------------------------------------------------
            [
                'category' => 'study-planning',
                'name' => 'Pomofocus',
                'purpose' => 'A browser-based Pomodoro timer that splits study into focused intervals separated by short breaks.',
                'selection_reason' => 'Chosen because it needs no account to start a session, which removes the usual excuse for not beginning.',
                'use_cases' => ['Time a 25-minute revision block', 'Pace a long problem set across an afternoon', 'Track how many focused blocks a topic actually took'],
                'usage_guidance' => 'Name the single task before you start the timer. Run one block, stop when the timer stops, and write one line about what you finished. Repeat rather than extending a block.',
                'limitations' => 'A timer paces work and cannot judge whether the work is correct or whether the task was the right one. Interval lengths that suit one person can be wrong for another.',
                'cost_note' => 'Core timer is free in the browser; a paid tier adds reporting and sync.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Nothing beyond a task label needs to be entered. Task labels are stored if you create an account.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://pomofocus.io/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'study-planning',
                'name' => 'Toggl Track',
                'purpose' => 'Time tracking that records how long study actually takes, per project or course.',
                'selection_reason' => 'Chosen because planning improves fastest when estimates are compared against measured time rather than memory.',
                'use_cases' => ['Measure real time spent per course', 'Compare an estimate against the actual duration', 'Find which subject is quietly consuming the week'],
                'usage_guidance' => 'Create one project per course. Start the timer when you start and stop it when you stop, then review the weekly report and adjust next week plan.',
                'limitations' => 'Tracked time measures presence, not learning. Long recorded hours can hide unfocused work.',
                'cost_note' => 'Has a free tier for individuals with paid team plans above it.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Project and entry names are stored on the vendor servers, so keep them generic rather than describing confidential work.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://toggl.com/track/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'study-planning',
                'name' => 'Google Calendar',
                'purpose' => 'Calendar scheduling for lectures, deadlines, and blocked study time.',
                'selection_reason' => 'Chosen because most institutions already issue an account, so timetable feeds and shared deadlines usually work without extra setup.',
                'use_cases' => ['Block study time against a deadline', 'Subscribe to a timetable feed', 'Set a reminder ahead of a submission'],
                'usage_guidance' => 'Put deadlines in first, then work backwards and block the study time needed to meet them. Treat a blocked hour as a commitment, not a suggestion.',
                'limitations' => 'A calendar shows intent, not capacity. Over-scheduling a week is easy and produces a plan you cannot keep.',
                'cost_note' => 'Free with a Google account; institutional accounts are usually covered by the institution licence.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Event titles and attendees are stored by Google. A personal account and an institutional account have different administrators and different retention rules.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://calendar.google.com/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'study-planning',
                'name' => 'Todoist',
                'purpose' => 'Task capture and scheduling with projects, due dates, and recurring items.',
                'selection_reason' => 'Chosen because fast capture on a phone is what keeps a task list trustworthy across a term.',
                'use_cases' => ['Capture an assignment the moment it is announced', 'Break a project into dated subtasks', 'Set a recurring weekly review'],
                'usage_guidance' => 'Capture everything in one inbox, then once a week assign each item a course project and a realistic date. An undated list stops being used.',
                'limitations' => 'A task manager does not prioritise for you. Long overdue lists demotivate more than they organise.',
                'cost_note' => 'Free tier with project and reminder limits; paid tiers raise them.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Task text syncs to vendor servers, so avoid pasting confidential assignment content into task titles.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://todoist.com/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'study-planning',
                'name' => 'Trello',
                'purpose' => 'Kanban boards for tracking multi-stage work such as a dissertation or group project.',
                'selection_reason' => 'Chosen because a visible board makes stalled work obvious in a way a flat list does not.',
                'use_cases' => ['Track a dissertation through drafting stages', 'Coordinate a group project', 'See what is blocked before a deadline'],
                'usage_guidance' => 'Keep three or four columns at most and put a hard limit on how many cards may sit in progress. Move cards yourself; a board nobody updates is worse than no board.',
                'limitations' => 'Boards drift out of date quickly when a group stops updating them, and the tool cannot tell you that has happened.',
                'cost_note' => 'Free tier for individuals and small boards, with paid tiers for larger workspaces.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Board content is stored by the vendor and visible to everyone invited, so check who is on a shared board before posting graded work.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://trello.com/',
                'provenance' => self::VENDOR_DOCS,
            ],

            // ---------------------------------------------------------------
            // Academic writing
            // ---------------------------------------------------------------
            [
                'category' => 'academic-writing',
                'name' => 'LanguageTool',
                'purpose' => 'Open-source grammar, spelling, and style checking across many languages.',
                'selection_reason' => 'Chosen because it is open source and can be self-hosted, which matters when institutional rules restrict sending drafts to third parties.',
                'use_cases' => ['Proofread a finished draft', 'Check writing in a second language', 'Catch repeated grammar mistakes you make'],
                'usage_guidance' => 'Run it on a draft you have already written. Read each suggestion and accept only the ones you understand, so the correction teaches you something.',
                'limitations' => 'It corrects surface language, not argument, evidence, or accuracy. Suggestions on technical or discipline-specific phrasing are frequently wrong.',
                'cost_note' => 'Free tier with a text length limit; a paid premium tier adds deeper checks. Self-hosting the open-source server is free.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Using the hosted service sends your text to the vendor. The self-hosted option keeps text on your own machine or server.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://languagetool.org/',
                'provenance' => self::OPEN_SOURCE,
            ],
            [
                'category' => 'academic-writing',
                'name' => 'Grammarly',
                'purpose' => 'Writing assistance covering grammar, clarity, tone, and, on some plans, generative drafting.',
                'selection_reason' => 'Chosen because it is one of the most widely installed writing assistants, so students need clear guidance on where its help stops being their own work.',
                'use_cases' => ['Proofread a submitted-ready draft', 'Tighten a sentence that reads awkwardly', 'Check tone in a formal email to a supervisor'],
                'usage_guidance' => 'Use it for correctness and clarity on text you wrote. Do not use its generative drafting features to produce assessed prose. Many institutions require you to declare AI writing assistance, so check your assignment brief first.',
                'limitations' => 'Clarity suggestions can flatten discipline-specific phrasing and occasionally change meaning. Its generative features can produce text that is not yours, which is where an integrity problem starts.',
                'cost_note' => 'Free tier covers basic correctness; paid individual and institutional plans add clarity, tone, and generative features. Some universities provide a licence.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Text is processed on vendor servers. Enterprise and education plans have different data terms from the consumer plan, so confirm which one your licence uses.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://www.grammarly.com/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'academic-writing',
                'name' => 'Hemingway Editor',
                'purpose' => 'Readability analysis that highlights long sentences, passive voice, and complex phrasing.',
                'selection_reason' => 'Chosen because it diagnoses sentence-level density without rewriting anything for you, which keeps the prose yours.',
                'use_cases' => ['Shorten an overlong paragraph', 'Reduce passive voice in a method section', 'Check readability of an abstract'],
                'usage_guidance' => 'Paste a section, look at what is highlighted, and rewrite it yourself. Ignore advice that would damage precision; academic writing sometimes needs a long sentence.',
                'limitations' => 'Its readability targets are aimed at general prose. Technical writing legitimately scores badly, so treat the grade as a prompt rather than a goal.',
                'cost_note' => 'A free web editor is available; a paid desktop application and paid AI features exist.'.self::PRICING_CAVEAT,
                'privacy_note' => 'The web editor processes pasted text in the browser session. Verify the current terms before pasting unpublished research.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://hemingwayapp.com/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'academic-writing',
                'name' => 'Overleaf',
                'purpose' => 'Collaborative LaTeX editing in the browser with compiled PDF output.',
                'selection_reason' => 'Chosen because it removes a local LaTeX installation as a barrier for maths, physics, and engineering write-ups.',
                'use_cases' => ['Write a lab report with equations', 'Use a journal or department LaTeX template', 'Co-author a paper with a supervisor'],
                'usage_guidance' => 'Start from your department template if one exists. Compile early and often so an error is one paragraph old rather than one chapter old.',
                'limitations' => 'LaTeX has a genuine learning curve, and compile errors can be cryptic. Version history depth and collaborator counts depend on the plan.',
                'cost_note' => 'Free tier with limited collaborators and history; paid tiers and institutional site licences add more.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Documents are stored on vendor servers unless you use a self-hosted or institutional deployment. Check which one your project uses before uploading unpublished results.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://www.overleaf.com/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'academic-writing',
                'name' => 'Google Docs',
                'purpose' => 'Collaborative word processing with comments, suggestions, and full revision history.',
                'selection_reason' => 'Chosen because its revision history is an honest record of how a draft was actually written, which helps if authorship is ever questioned.',
                'use_cases' => ['Draft an essay with supervisor comments', 'Co-write a group report', 'Show the drafting history of a piece of work'],
                'usage_guidance' => 'Write in the document rather than pasting a finished block in, so the version history reflects your real process. Use suggestion mode for feedback rather than direct edits.',
                'limitations' => 'Weak for long documents with heavy formatting, and offline editing needs setup. Sharing settings are easy to get wrong.',
                'cost_note' => 'Free with a Google account; institutional accounts are covered by the institution licence.'.self::PRICING_CAVEAT,
                'privacy_note' => 'A link set to anyone-with-the-link is genuinely public. Check the sharing setting on every document containing graded work.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://docs.google.com/',
                'provenance' => self::VENDOR_DOCS,
            ],

            // ---------------------------------------------------------------
            // Research and sources
            // ---------------------------------------------------------------
            [
                'category' => 'research-and-sources',
                'name' => 'Google Scholar',
                'purpose' => 'Search across scholarly literature with citation counts and links to available versions.',
                'selection_reason' => 'Chosen because its breadth makes it a reasonable first sweep before moving to a curated database.',
                'use_cases' => ['Find the seminal paper on a topic', 'Follow citations forward from a key paper', 'Locate an accessible version of a paywalled article'],
                'usage_guidance' => 'Search, then use cited-by to move forward in time and the reference list to move backward. Link your library account so full-text links resolve.',
                'limitations' => 'Coverage and ranking are opaque, and it indexes some non-peer-reviewed material. Citation counts are not a quality measure.',
                'cost_note' => 'Free to search, though many results still sit behind publisher paywalls that your library may cover.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Searches are associated with your Google session if you are signed in.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://scholar.google.com/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'research-and-sources',
                'name' => 'Semantic Scholar',
                'purpose' => 'A free academic search engine from the Allen Institute for AI with citation context and paper summaries.',
                'selection_reason' => 'Chosen because it is run by a non-profit and exposes an open API and dataset, so its coverage can be inspected rather than guessed at.',
                'use_cases' => ['Screen a long candidate list quickly', 'See how a paper is cited, not just how often', 'Export metadata for a reference manager'],
                'usage_guidance' => 'Use the citation context to judge whether a paper supports or disputes the claim you care about, then read the source itself before citing it.',
                'limitations' => 'Automatic summaries are generated and can misstate a finding. Coverage is strongest in computer science and biomedicine.',
                'cost_note' => 'Free to use, including the public API within its documented rate limits.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Search activity is handled under the non-profit privacy policy; an account is optional for basic search.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://www.semanticscholar.org/',
                'provenance' => self::INSTITUTION,
            ],
            [
                'category' => 'research-and-sources',
                'name' => 'Connected Papers',
                'purpose' => 'Builds a visual graph of papers related to one seed paper by citation similarity.',
                'selection_reason' => 'Chosen because it surfaces adjacent work a keyword search misses when a field uses different vocabulary for the same idea.',
                'use_cases' => ['Map a field before a literature review', 'Find prior work a keyword search missed', 'Identify the clustered classics on a topic'],
                'usage_guidance' => 'Start from one paper you already trust, generate the graph, then read the nearest nodes. Use it to find candidates, never as the review itself.',
                'limitations' => 'The graph reflects citation similarity, not quality or relevance to your specific question. A well-connected paper can still be a poor source for you.',
                'cost_note' => 'A limited number of free graphs per period with paid plans above that.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Only the seed paper identifier is needed; no manuscript content is uploaded.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://www.connectedpapers.com/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'research-and-sources',
                'name' => 'Elicit',
                'purpose' => 'An AI research assistant that searches papers and extracts structured data such as populations, methods, and outcomes into a table.',
                'selection_reason' => 'Chosen because structured extraction across many papers is genuinely slow by hand, and its output links back to the source passage.',
                'use_cases' => ['Screen abstracts for a systematic review', 'Build a comparison table of study methods', 'Summarise what a set of papers measured'],
                'usage_guidance' => 'Treat every extracted cell as a claim to verify. Open the linked paper and confirm the value before it reaches your write-up, and cite the paper rather than the tool.',
                'limitations' => 'Extraction errors and omissions happen, and language-model summaries can state something a paper does not. It is not a substitute for reading the sources you cite.',
                'cost_note' => 'A free tier with monthly credits and paid tiers for higher volume.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Queries and any uploaded PDFs are processed on vendor infrastructure that includes third-party model providers.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://elicit.com/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'research-and-sources',
                'name' => 'Consensus',
                'purpose' => 'Searches research papers and surfaces what the aggregate of studies says about a yes-or-no research question.',
                'selection_reason' => 'Chosen because it pushes students toward the weight of evidence rather than the single paper that agrees with them.',
                'use_cases' => ['Check whether a claim is actually supported', 'Find both supporting and contradicting studies', 'Sanity-check an assumption before building on it'],
                'usage_guidance' => 'Ask a specific empirical question, then read the underlying papers on both sides. Cite the studies, never the aggregate summary.',
                'limitations' => 'Aggregating findings hides study quality, sample size, and conflicting methods. A consensus display is not a meta-analysis.',
                'cost_note' => 'Free tier with limited searches; paid tiers raise limits.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Queries are processed on vendor infrastructure including third-party model providers.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://consensus.app/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'research-and-sources',
                'name' => 'OpenAlex',
                'purpose' => 'A fully open catalogue of scholarly works, authors, institutions, and citation links, available as data and API.',
                'selection_reason' => 'Chosen because it is open data, so coverage claims can be checked rather than taken on trust, and it has no paywall.',
                'use_cases' => ['Check a publication record', 'Pull citation data for a bibliometrics exercise', 'Resolve a work identifier reliably'],
                'usage_guidance' => 'Use it when you need metadata you can reproduce and cite. Query the API directly if you need a defensible, repeatable search.',
                'limitations' => 'It is a metadata catalogue, not a full-text library, and automatically derived author or institution links contain errors.',
                'cost_note' => 'Free and openly licensed, with a paid premium service for high-volume users.'.self::PRICING_CAVEAT,
                'privacy_note' => 'No account is needed for basic use and no document content is uploaded.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://openalex.org/',
                'provenance' => self::INSTITUTION,
            ],

            // ---------------------------------------------------------------
            // Citation and referencing
            // ---------------------------------------------------------------
            [
                'category' => 'citation-and-referencing',
                'name' => 'Zotero',
                'purpose' => 'Open-source reference manager that collects sources, stores PDFs, and generates bibliographies in thousands of styles.',
                'selection_reason' => 'Chosen because it is open source, stores your library locally by default, and is the lowest-risk way to stop losing references.',
                'use_cases' => ['Collect sources while browsing', 'Generate a bibliography in a required style', 'Keep annotated PDFs with their metadata'],
                'usage_guidance' => 'Install the browser connector and save every source as you find it. Check the captured metadata immediately; a wrong year saved now is a wrong citation later.',
                'limitations' => 'Automatically captured metadata is frequently incomplete or wrong and must be corrected by hand. Style output still needs a final manual check against your handbook.',
                'cost_note' => 'The software is free and open source. Storage beyond a small free sync quota is paid, and you can sync to your own storage instead.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Your library stays local unless you enable sync. Group libraries are visible to their members.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://www.zotero.org/',
                'provenance' => self::OPEN_SOURCE,
            ],
            [
                'category' => 'citation-and-referencing',
                'name' => 'ZoteroBib',
                'purpose' => 'A free web bibliography builder that produces a formatted reference list without an account or install.',
                'selection_reason' => 'Chosen because a one-off essay does not justify setting up a whole reference manager, and this covers that case honestly.',
                'use_cases' => ['Build a reference list for a single essay', 'Format one awkward source correctly', 'Convert a list between citation styles'],
                'usage_guidance' => 'Paste a DOI, ISBN, or URL, then check every generated field against the source before you copy the list out.',
                'limitations' => 'Generated entries inherit whatever metadata the source publishes, so errors pass straight through. There is no library to return to later.',
                'cost_note' => 'Free to use with no account required.'.self::PRICING_CAVEAT,
                'privacy_note' => 'The working bibliography is held in your browser local storage rather than an account.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://zbib.org/',
                'provenance' => self::OPEN_SOURCE,
            ],
            [
                'category' => 'citation-and-referencing',
                'name' => 'Mendeley Reference Manager',
                'purpose' => 'Reference management with PDF annotation and word-processor citation plugins, published by Elsevier.',
                'selection_reason' => 'Chosen because some departments and supervisors standardise on it, so shared libraries are easier to join than to fight.',
                'use_cases' => ['Share a reference library with a supervisor', 'Insert citations while writing in Word', 'Annotate PDFs alongside their metadata'],
                'usage_guidance' => 'Verify each imported record before citing it, and agree one citation style with your supervisor before the library grows.',
                'limitations' => 'It is published by a large commercial publisher, and features and desktop applications have changed significantly between versions. Metadata still needs manual correction.',
                'cost_note' => 'Free to use with a storage quota; larger storage is paid and some institutions provide an upgraded plan.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Libraries sync to the publisher cloud by default. Review the publisher privacy terms if that matters for your work.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://www.mendeley.com/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'citation-and-referencing',
                'name' => 'Purdue OWL',
                'purpose' => 'A free university-published writing lab documenting APA, MLA, and Chicago citation and formatting rules.',
                'selection_reason' => 'Chosen because when a generated citation looks wrong you need an authoritative human-readable rule to check it against.',
                'use_cases' => ['Check an unusual source type', 'Confirm in-text citation format', 'Look up a formatting rule for a paper'],
                'usage_guidance' => 'Use it as the reference of record when a citation manager and your handbook disagree. Your own institution handbook still wins where it differs.',
                'limitations' => 'It documents style guides but is not the style guide itself, and lags behind new editions. It cannot check your document for you.',
                'cost_note' => 'Free, published by Purdue University.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Read-only reference material; nothing about your work is submitted.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://owl.purdue.edu/owl/purdue_owl.html',
                'provenance' => self::INSTITUTION,
            ],

            // ---------------------------------------------------------------
            // Exam preparation
            // ---------------------------------------------------------------
            [
                'category' => 'exam-preparation',
                'name' => 'Anki',
                'purpose' => 'Open-source spaced-repetition flashcards with a scheduling algorithm that times reviews to the edge of forgetting.',
                'selection_reason' => 'Chosen because spaced retrieval practice is one of the best-evidenced study techniques and this is the most transparent implementation of it.',
                'use_cases' => ['Memorise vocabulary or terminology', 'Retain formulas across a term', 'Drill facts for a professional exam'],
                'usage_guidance' => 'Write your own cards; the act of writing them is most of the learning. Keep each card to a single fact and review daily rather than in bursts.',
                'limitations' => 'Excellent for recall of discrete facts and poor for reasoning, synthesis, or essay skills. Downloaded shared decks are much less effective than cards you wrote.',
                'cost_note' => 'Free on desktop, Android, and web; the iOS application is a paid purchase that funds the project.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Collections are stored locally unless you enable the optional sync service.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://apps.ankiweb.net/',
                'provenance' => self::OPEN_SOURCE,
            ],
            [
                'category' => 'exam-preparation',
                'name' => 'Quizlet',
                'purpose' => 'Flashcards and practice modes with a large library of user-created study sets.',
                'selection_reason' => 'Chosen because its shared sets get a student started quickly, provided they treat the content as unverified.',
                'use_cases' => ['Drill terminology before a test', 'Practise in short mobile sessions', 'Turn a glossary into recall practice'],
                'usage_guidance' => 'Check any shared set against your own lecture notes before trusting it, or build your own set from those notes.',
                'limitations' => 'User-created sets contain errors and are not reviewed. Some study modes are behind a paid tier, and using a shared set for a graded quiz may breach your assessment rules.',
                'cost_note' => 'Free tier with adverts and limited modes; paid tiers unlock more.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Sets you create can be public by default, so check visibility before uploading course material you do not own.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://quizlet.com/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'exam-preparation',
                'name' => 'Khan Academy',
                'purpose' => 'Free non-profit lessons and practice exercises with immediate feedback across maths, science, and other subjects.',
                'selection_reason' => 'Chosen because it pairs explanation with graded practice, which is the combination that actually moves exam performance.',
                'use_cases' => ['Rebuild a weak prerequisite topic', 'Practise problems with instant feedback', 'Get an alternative explanation of a concept'],
                'usage_guidance' => 'Use the practice exercises rather than only the videos. Attempt each problem before revealing the worked solution.',
                'limitations' => 'Coverage is strongest at school and early undergraduate level and thins out for advanced topics. It will not match your specific syllabus.',
                'cost_note' => 'Free, funded as a non-profit by donations.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Progress tracking requires an account; learning can be done signed out.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://www.khanacademy.org/',
                'provenance' => self::INSTITUTION,
            ],
            [
                'category' => 'exam-preparation',
                'name' => 'RemNote',
                'purpose' => 'Note taking with spaced repetition built in, so flashcards are generated from the notes as you write them.',
                'selection_reason' => 'Chosen because the gap between taking notes and making cards is where most revision plans die.',
                'use_cases' => ['Turn lecture notes into cards as you write', 'Keep definitions and their practice in one place', 'Revise from your own hierarchy of notes'],
                'usage_guidance' => 'Write notes normally and mark the parts worth remembering as you go, then review daily. Do not card everything; be selective.',
                'limitations' => 'The combined model takes time to learn and can encourage carding trivia. Advanced features sit behind a paid tier.',
                'cost_note' => 'Free tier with limits and paid tiers above it.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Notes sync to vendor servers by default.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://www.remnote.com/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'exam-preparation',
                'name' => 'Brainscape',
                'purpose' => 'Flashcards with confidence-based repetition, where you rate how well you knew each answer.',
                'selection_reason' => 'Chosen because forcing an explicit confidence rating surfaces the illusion of knowing, which is the main failure mode in revision.',
                'use_cases' => ['Rate confidence across a topic', 'Focus revision on genuinely weak cards', 'Drill on a phone between classes'],
                'usage_guidance' => 'Rate honestly. A generous rating removes the card from rotation and is the fastest way to make the tool useless.',
                'limitations' => 'Like all flashcard tools it trains recall rather than application, and much of the shared library is behind a paid tier.',
                'cost_note' => 'Free tier with paid subscription for premium decks and features.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Decks and progress sync to vendor servers.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://www.brainscape.com/',
                'provenance' => self::VENDOR_DOCS,
            ],

            // ---------------------------------------------------------------
            // Note taking
            // ---------------------------------------------------------------
            [
                'category' => 'note-taking',
                'name' => 'Obsidian',
                'purpose' => 'A local-first note application storing plain Markdown files with links between notes.',
                'selection_reason' => 'Chosen because notes stay as plain files on your own disk, so a term of work does not depend on a company continuing to exist.',
                'use_cases' => ['Build linked notes across a module', 'Keep reading notes next to lecture notes', 'Work offline with no account'],
                'usage_guidance' => 'Keep one vault per degree rather than per module so links cross subjects. Back the folder up like any other coursework.',
                'limitations' => 'No structure is imposed, so an unmanaged vault becomes a pile. Sync and publishing are paid add-ons unless you use your own file sync.',
                'cost_note' => 'Free for personal use; paid add-ons for official sync and publishing, and a commercial-use licence exists.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Files stay on your device by default. Community plugins are third-party code, so review what you install.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://obsidian.md/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'note-taking',
                'name' => 'Notion',
                'purpose' => 'A workspace combining documents, databases, and shared pages.',
                'selection_reason' => 'Chosen because its databases handle course and reading tracking better than a flat notes app.',
                'use_cases' => ['Track readings in a database', 'Keep a shared group project wiki', 'Build a per-course dashboard'],
                'usage_guidance' => 'Start with a plain page and add structure only when the flat version stops working. Building the system is not studying.',
                'limitations' => 'Search across a large workspace is slow, offline use is limited, and export fidelity is imperfect, so plan for the lock-in.',
                'cost_note' => 'Free personal plan; paid plans for collaboration, and a free education plan has been offered to verified students.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Content is stored on vendor servers and published pages are genuinely public on the open web.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://www.notion.com/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'note-taking',
                'name' => 'Microsoft OneNote',
                'purpose' => 'Freeform notebooks supporting typing, ink, audio, and images on a page canvas.',
                'selection_reason' => 'Chosen because handwriting and diagram-heavy subjects need a canvas rather than a line-based editor, and most institutions already licence it.',
                'use_cases' => ['Handwrite notes on a tablet in lectures', 'Annotate a slide deck', 'Record audio alongside written notes'],
                'usage_guidance' => 'One notebook per course with a section per week keeps search useful. Confirm recording is permitted before capturing a lecture.',
                'limitations' => 'Export options are weak and search over handwriting is unreliable. Feature sets differ noticeably between platforms.',
                'cost_note' => 'Free tier available; deeper storage arrives through Microsoft 365, which many institutions licence for students.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Notebooks sync to OneDrive. An institutional tenant is administered by your institution, which may retain access.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://www.onenote.com/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'note-taking',
                'name' => 'Logseq',
                'purpose' => 'An open-source outliner storing notes as local Markdown files with daily journals and backlinks.',
                'selection_reason' => 'Chosen because a daily journal lowers the barrier to capturing something, and outlining suits lecture notes.',
                'use_cases' => ['Capture notes into a daily journal', 'Outline a lecture in real time', 'Link a claim back to its source note'],
                'usage_guidance' => 'Write into today journal and link out to topic pages rather than deciding filing structure in the moment.',
                'limitations' => 'The outline-only model does not suit long prose, and the project has undergone significant architectural change, so check the current release status before committing a term of notes.',
                'cost_note' => 'Free and open source; an optional paid sync service exists.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Notes are local files by default.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://logseq.com/',
                'provenance' => self::OPEN_SOURCE,
            ],
            [
                'category' => 'note-taking',
                'name' => 'Google Keep',
                'purpose' => 'Lightweight note and checklist capture that syncs across devices.',
                'selection_reason' => 'Chosen because the fastest capture tool is the one that opens instantly, and a heavyweight app loses that race.',
                'use_cases' => ['Capture a thought before it is lost', 'Photograph a whiteboard after a seminar', 'Keep a short checklist for a submission'],
                'usage_guidance' => 'Use it as an inbox only. Move anything that matters into your real notes within a day.',
                'limitations' => 'No hierarchy, weak formatting, and no long-document support. It is capture, not a knowledge base.',
                'cost_note' => 'Free with a Google account.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Notes and photos sync to Google servers under the account privacy terms.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://keep.google.com/',
                'provenance' => self::VENDOR_DOCS,
            ],

            // ---------------------------------------------------------------
            // Maths and quantitative
            // ---------------------------------------------------------------
            [
                'category' => 'math-and-quantitative',
                'name' => 'Wolfram Alpha',
                'purpose' => 'A computational engine that answers mathematical, scientific, and data queries, with step-by-step solutions on paid plans.',
                'selection_reason' => 'Chosen because checking an answer you already derived is a legitimate and effective use of a computational engine.',
                'use_cases' => ['Check an integral you computed by hand', 'Verify a unit conversion', 'Plot a function to sanity-check a result'],
                'usage_guidance' => 'Do the working yourself first, then verify. If the answers differ, find your error rather than copying the output.',
                'limitations' => 'Ambiguous input is silently reinterpreted, so a confident answer can be to a different question. Submitting its step-by-step working as your own is an integrity violation in most courses.',
                'cost_note' => 'Free for basic queries; step-by-step solutions require a paid subscription.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Queries are sent to and processed by vendor servers.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://www.wolframalpha.com/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'math-and-quantitative',
                'name' => 'Desmos Graphing Calculator',
                'purpose' => 'A free browser graphing calculator with sliders, tables, and shareable graphs.',
                'selection_reason' => 'Chosen because dragging a parameter and watching a curve respond builds intuition no static worked example can.',
                'use_cases' => ['See how a parameter changes a curve', 'Check a hand-drawn sketch', 'Build a figure for a report'],
                'usage_guidance' => 'Predict what the graph will look like before you plot it. The gap between prediction and plot is the lesson.',
                'limitations' => 'Graphing and light computation only; it is not a computer algebra system and will not do symbolic proof.',
                'cost_note' => 'The graphing calculator is free to use.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Graphs can be used without an account; saving and sharing stores them on vendor servers.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://www.desmos.com/calculator',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'math-and-quantitative',
                'name' => 'GeoGebra',
                'purpose' => 'Free interactive geometry, algebra, statistics, and calculus tools used widely in education.',
                'selection_reason' => 'Chosen because dynamic geometry lets you test whether a property holds in general rather than in one drawn case.',
                'use_cases' => ['Explore a geometric construction', 'Visualise a statistical distribution', 'Build an interactive figure for a presentation'],
                'usage_guidance' => 'Construct the object with real constraints rather than positioning points by eye, then drag it and see what stays true.',
                'limitations' => 'A dragged construction suggests a property; it does not prove one. The proof is still yours to write.',
                'cost_note' => 'Free for students and teachers, published by a non-profit organisation.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Works without an account; saved materials are stored on its servers and can be public.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://www.geogebra.org/',
                'provenance' => self::INSTITUTION,
            ],
            [
                'category' => 'math-and-quantitative',
                'name' => 'Symbolab',
                'purpose' => 'A step-by-step maths solver covering algebra, calculus, and linear algebra.',
                'selection_reason' => 'Chosen because a worked path through a problem you are already stuck on is useful, provided the honest limits are stated.',
                'use_cases' => ['See a worked method after your own attempt', 'Identify which step you got wrong', 'Check a derivative or limit'],
                'usage_guidance' => 'Attempt the problem fully first, compare the steps, then redo it unaided. If you cannot redo it, you have not learned it.',
                'limitations' => 'Copying its worked solution into assessed work is plagiarism in most courses. Steps are sometimes non-standard or skip justification your marker expects.',
                'cost_note' => 'Limited free use with full step-by-step behind a paid subscription.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Entered problems are processed on vendor servers.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://www.symbolab.com/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'math-and-quantitative',
                'name' => 'PhET Interactive Simulations',
                'purpose' => 'Free research-based science and maths simulations from the University of Colorado Boulder.',
                'selection_reason' => 'Chosen because the simulations are designed and tested by an education research group rather than assembled for engagement.',
                'use_cases' => ['Build intuition for a physical system', 'Run an experiment you cannot run in a lab', 'Test a prediction before a practical'],
                'usage_guidance' => 'Write your prediction down, run the simulation, then explain any difference. The explanation is the learning.',
                'limitations' => 'Simulations are idealised models and omit real experimental error. They do not replace laboratory work.',
                'cost_note' => 'Free and openly licensed, funded by a university and donors.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Simulations run in the browser and require no account for normal use.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://phet.colorado.edu/',
                'provenance' => self::INSTITUTION,
            ],

            // ---------------------------------------------------------------
            // Coding and CS
            // ---------------------------------------------------------------
            [
                'category' => 'coding-and-cs',
                'name' => 'Visual Studio Code',
                'purpose' => 'A free extensible code editor with debugging, version control, and language support.',
                'selection_reason' => 'Chosen because a debugger you can actually step through teaches more about a bug than any amount of print statements.',
                'use_cases' => ['Write and debug a coursework program', 'Step through code to understand a failure', 'Work with Git without leaving the editor'],
                'usage_guidance' => 'Learn the debugger early. Set a breakpoint and inspect state rather than guessing at what a line does.',
                'limitations' => 'Extensions are third-party code with real access to your files, and the official Microsoft build includes telemetry that can be disabled.',
                'cost_note' => 'Free to download and use; some connected services and AI features have separate paid plans.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Telemetry is on by default in the Microsoft build and can be turned off in settings. Vet extensions before installing them.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://code.visualstudio.com/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'coding-and-cs',
                'name' => 'GitHub Copilot',
                'purpose' => 'An AI coding assistant that suggests completions and whole functions inside an editor.',
                'selection_reason' => 'Listed with a warning rather than a recommendation: it is widely used, and students need to know exactly where it crosses an academic-integrity line.',
                'use_cases' => ['Explain an unfamiliar library call', 'Draft boilerplate on a personal project', 'Suggest a test case you then verify'],
                'usage_guidance' => 'Check your course policy before enabling it; many programming modules prohibit it outright for assessed work. Never submit generated code you cannot explain line by line.',
                'limitations' => 'Generated code is frequently subtly wrong, may reproduce patterns from training data with licensing implications, and using it on assessed work without permission is misconduct in many programmes.',
                'cost_note' => 'Paid subscription with a limited free tier; verified students and some educators have been eligible for free access through GitHub Education.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Code context is sent to the vendor for completion. Data-retention behaviour differs between individual, business, and education plans, so confirm which applies to you.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://github.com/features/copilot',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'coding-and-cs',
                'name' => 'Google Colab',
                'purpose' => 'Hosted Jupyter notebooks that run Python in the browser with optional accelerated hardware.',
                'selection_reason' => 'Chosen because it removes environment setup, which is where most students lose their first week of a data course.',
                'use_cases' => ['Run a data analysis without local setup', 'Share a reproducible notebook with a marker', 'Use a GPU for a small model'],
                'usage_guidance' => 'Restart and run the whole notebook top to bottom before submitting; a notebook that only works out of order is not reproducible.',
                'limitations' => 'Free sessions are time-limited and can be reclaimed without warning, losing unsaved state. Resource availability is not guaranteed.',
                'cost_note' => 'Free tier with variable resources; paid tiers buy more reliable compute.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Notebooks and uploaded data are stored in Google infrastructure, and sharing settings work like any Drive file.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://colab.research.google.com/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'coding-and-cs',
                'name' => 'Exercism',
                'purpose' => 'Free programming practice exercises across many languages with volunteer human mentoring.',
                'selection_reason' => 'Chosen because human feedback on working code is rare, free, and much more useful than another passing test.',
                'use_cases' => ['Practise a new language systematically', 'Get human feedback on style', 'Rebuild fundamentals before an exam'],
                'usage_guidance' => 'Solve first, then request mentoring, then rewrite based on the feedback. The rewrite is where the improvement happens.',
                'limitations' => 'Mentor response times vary because mentors are volunteers, and exercises are language practice rather than course material.',
                'cost_note' => 'Free, run by a non-profit funded by donations.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Submitted solutions are visible to mentors and can be published to your public profile.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://exercism.org/',
                'provenance' => self::INSTITUTION,
            ],
            [
                'category' => 'coding-and-cs',
                'name' => 'Replit',
                'purpose' => 'A browser development environment that runs and hosts projects in many languages with live collaboration.',
                'selection_reason' => 'Chosen because pair programming in a shared editor works well for study groups on locked-down machines.',
                'use_cases' => ['Pair program with a classmate', 'Run code on a device you cannot install on', 'Share a runnable snippet with a tutor'],
                'usage_guidance' => 'Confirm whether a project is public before putting coursework in it, and check your course rules on shared editing for assessed work.',
                'limitations' => 'Free projects have historically defaulted to public visibility, and free compute is limited. Its AI features raise the same integrity questions as any code generator.',
                'cost_note' => 'Free tier with limited resources and paid tiers above it.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Code runs and is stored on vendor infrastructure, and public projects are readable by anyone.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://replit.com/',
                'provenance' => self::VENDOR_DOCS,
            ],

            // ---------------------------------------------------------------
            // Presentations and visuals
            // ---------------------------------------------------------------
            [
                'category' => 'presentations-and-visuals',
                'name' => 'Canva',
                'purpose' => 'Template-driven design for slides, posters, and figures in the browser.',
                'selection_reason' => 'Chosen because a conference poster has real layout constraints and a good template solves them faster than starting from a blank page.',
                'use_cases' => ['Build an academic poster', 'Design consistent slides quickly', 'Produce a figure for a report'],
                'usage_guidance' => 'Pick one template and stay inside it. Check the licence on any stock asset you place in work that will be published.',
                'limitations' => 'Templates push visual polish ahead of argument, and it is easy to produce a slide that looks finished but says nothing. Some assets and features are paid.',
                'cost_note' => 'Free tier with paid Pro features; a free education plan has been available for eligible students and teachers.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Designs are stored on vendor servers and share links can be public.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://www.canva.com/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'presentations-and-visuals',
                'name' => 'Google Slides',
                'purpose' => 'Collaborative presentation editing with comments, speaker notes, and revision history.',
                'selection_reason' => 'Chosen because group presentations fail on merge conflicts more often than on content, and simultaneous editing removes that failure.',
                'use_cases' => ['Build a group presentation together', 'Leave feedback on a rehearsal deck', 'Present from any machine with a browser'],
                'usage_guidance' => 'Write the speaker notes before the slides. If the notes do not make an argument, the slides will not either.',
                'limitations' => 'Weaker than desktop presentation software for animation, precise layout, and offline reliability. Complex imported decks can reflow.',
                'cost_note' => 'Free with a Google account; institutional accounts covered by the institution licence.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Check the sharing setting before a deck contains unpublished results; anyone-with-the-link is public.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://slides.google.com/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'presentations-and-visuals',
                'name' => 'Excalidraw',
                'purpose' => 'An open-source whiteboard for hand-drawn-style diagrams, with local and collaborative modes.',
                'selection_reason' => 'Chosen because a deliberately sketchy diagram signals a work in progress and invites the correction a polished figure suppresses.',
                'use_cases' => ['Sketch a system architecture', 'Explain a concept on a shared canvas', 'Draft a figure before making it properly'],
                'usage_guidance' => 'Use it for thinking and explaining. Redraw the final figure in a tool with precise control if the diagram is going into a submission.',
                'limitations' => 'Not suited to precise technical drawing, and the hand-drawn style is inappropriate for some formal submissions.',
                'cost_note' => 'Free and open source, with a paid hosted collaboration product.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Drawings stay in your browser unless you start a shared session or save to the hosted service.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://excalidraw.com/',
                'provenance' => self::OPEN_SOURCE,
            ],
            [
                'category' => 'presentations-and-visuals',
                'name' => 'diagrams.net',
                'purpose' => 'Free diagramming for flowcharts, entity relationship diagrams, UML, and network diagrams.',
                'selection_reason' => 'Chosen because coursework often requires standard notation, and it ships the correct shape libraries without a subscription.',
                'use_cases' => ['Draw a UML class diagram', 'Produce an entity relationship diagram', 'Document a process as a flowchart'],
                'usage_guidance' => 'Pick the shape library that matches the notation your marker expects, and export as a vector format so the figure stays sharp in a PDF.',
                'limitations' => 'The interface is dense, and it does not check that your notation is semantically correct.',
                'cost_note' => 'Free to use, including the desktop application.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Diagrams can be stored locally or in the storage provider you choose; the desktop application keeps files on your machine.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://www.drawio.com/',
                'provenance' => self::OPEN_SOURCE,
            ],

            // ---------------------------------------------------------------
            // Accessibility and inclusion
            // ---------------------------------------------------------------
            [
                'category' => 'accessibility-and-inclusion',
                'name' => 'NVDA Screen Reader',
                'purpose' => 'A free open-source screen reader for Windows that speaks on-screen content and supports braille displays.',
                'selection_reason' => 'Chosen because it removes cost as a barrier to a screen reader, and it is also the practical way for sighted students to test their own documents.',
                'use_cases' => ['Read course material aloud', 'Navigate a virtual learning environment non-visually', 'Test whether a document you produced is actually navigable'],
                'usage_guidance' => 'Learn the heading navigation shortcut first; well-structured documents become fast and badly structured ones become obviously broken.',
                'limitations' => 'Windows only, and it exposes rather than fixes inaccessible material. Poorly tagged PDFs remain unreadable.',
                'cost_note' => 'Free and open source, funded by donations to a non-profit.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Runs locally on your machine and does not send document content anywhere.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://www.nvaccess.org/',
                'provenance' => self::OPEN_SOURCE,
            ],
            [
                'category' => 'accessibility-and-inclusion',
                'name' => 'Microsoft Immersive Reader',
                'purpose' => 'A reading mode offering read-aloud, line focus, syllable breaks, and adjustable spacing across Microsoft applications.',
                'selection_reason' => 'Chosen because it is built into software institutions already licence, so it needs no separate approval or purchase.',
                'use_cases' => ['Read a dense paper with line focus', 'Listen to a chapter while following the text', 'Increase spacing for easier reading'],
                'usage_guidance' => 'Adjust spacing and font before assuming a text is too hard. Small typographic changes make a large difference for many readers.',
                'limitations' => 'Only available inside supporting Microsoft products, and cannot rescue a scanned PDF with no text layer.',
                'cost_note' => 'Included in Microsoft products that support it, so covered by an existing institutional licence.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Text is processed by the host Microsoft application under that application data terms.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://www.microsoft.com/en-us/education/products/learning-tools',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'accessibility-and-inclusion',
                'name' => 'Otter.ai',
                'purpose' => 'Automatic speech-to-text transcription for lectures, interviews, and meetings.',
                'selection_reason' => 'Chosen because a searchable transcript is a genuine access need for many students, but recording carries obligations worth stating loudly.',
                'use_cases' => ['Transcribe a recorded lecture', 'Transcribe a research interview', 'Search back through a seminar discussion'],
                'usage_guidance' => 'Get explicit permission before recording any lecture or interview. Correct the transcript before quoting from it; automatic transcription mishears technical terms and names constantly.',
                'limitations' => 'Accuracy drops sharply with accents, overlapping speakers, poor audio, and specialist vocabulary. Automatic summaries can misattribute who said what.',
                'cost_note' => 'Free tier with a monthly transcription minute allowance; paid tiers raise it.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Audio is uploaded and processed on vendor servers. Recording people without consent may breach both your institution rules and data-protection law.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://otter.ai/',
                'provenance' => self::VENDOR_DOCS,
            ],
            [
                'category' => 'accessibility-and-inclusion',
                'name' => 'WAVE Web Accessibility Evaluation Tool',
                'purpose' => 'Evaluates a web page for accessibility problems such as missing alternative text, contrast failures, and broken heading structure.',
                'selection_reason' => 'Chosen because students building web coursework need an objective check against published standards rather than an opinion.',
                'use_cases' => ['Check a coursework website for accessibility errors', 'Verify colour contrast', 'Check heading structure on a page you built'],
                'usage_guidance' => 'Fix errors first, then review alerts one by one. Follow up with a keyboard-only pass, because automated checks miss most real problems.',
                'limitations' => 'Automated checking catches only a minority of accessibility barriers. A clean report does not mean an accessible page.',
                'cost_note' => 'Free browser extension and web checker from a non-profit; paid API and subscription options exist.'.self::PRICING_CAVEAT,
                'privacy_note' => 'The web version sends the page address to the service; the browser extension evaluates the page locally.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://wave.webaim.org/',
                'provenance' => self::INSTITUTION,
            ],
            [
                'category' => 'accessibility-and-inclusion',
                'name' => 'Speechify',
                'purpose' => 'Text-to-speech that reads documents, web pages, and scanned text aloud at adjustable speed.',
                'selection_reason' => 'Chosen because listening while reading helps many students with dyslexia and reduces fatigue on long reading lists.',
                'use_cases' => ['Listen to a reading list while commuting', 'Read along with audio to sustain focus', 'Convert a scanned handout to speech'],
                'usage_guidance' => 'Follow the text while listening rather than listening passively, and slow the speed down for material you are being assessed on.',
                'limitations' => 'Comprehension at high playback speed is often worse than it feels. Scanned-document quality varies, and the best voices are on paid tiers.',
                'cost_note' => 'Free tier with basic voices; premium voices and higher limits are a paid subscription.'.self::PRICING_CAVEAT,
                'privacy_note' => 'Documents you import are processed on vendor infrastructure.'.self::PRIVACY_CAVEAT,
                'external_url' => 'https://speechify.com/',
                'provenance' => self::VENDOR_DOCS,
            ],
        ];
    }

    /**
     * @return list<array{category: string, title: string, purpose: string, template_body: string, placeholders: list<string>, expected_output: string, integrity_note: string, provenance: string, related_tools: list<string>}>
     */
    public static function prompts(): array
    {
        return [
            [
                'category' => 'study-planning',
                'title' => 'Plan a Study Session',
                'purpose' => 'Turn a vague study goal into a bounded, timed plan.',
                'template_body' => 'Help me plan a {{minutes}}-minute study session for {{course_name}} focused on {{topic}}. List the steps and a short break.',
                'placeholders' => ['minutes', 'course_name', 'topic'],
                'expected_output' => 'A short ordered plan with time estimates and one break.',
                'integrity_note' => 'Use the plan to organize your own study; do the learning yourself.',
                'provenance' => self::IN_HOUSE,
                'related_tools' => ['Pomofocus', 'Google Calendar'],
            ],
            [
                'category' => 'study-planning',
                'title' => 'Build a Week Plan Around Deadlines',
                'purpose' => 'Work backwards from fixed deadlines to a realistic weekly schedule.',
                'template_body' => 'I have these deadlines: {{deadlines}}. I have about {{hours_available}} study hours this week across {{courses}}. Propose a week plan that works backwards from the deadlines, flags anything that does not fit, and leaves one unscheduled buffer block.',
                'placeholders' => ['deadlines', 'hours_available', 'courses'],
                'expected_output' => 'A day-by-day plan, an explicit list of anything that does not fit in the available hours, and one buffer block.',
                'integrity_note' => 'A plan is a commitment you keep yourself. If the plan says the work does not fit, talk to your tutor rather than hiding the gap.',
                'provenance' => self::IN_HOUSE,
                'related_tools' => ['Google Calendar', 'Todoist'],
            ],
            [
                'category' => 'academic-writing',
                'title' => 'Outline an Essay',
                'purpose' => 'Produce a structured outline you then write yourself.',
                'template_body' => 'Give me a section-by-section outline for an essay on {{thesis}} for {{course_name}}, with one prompt per section to research.',
                'placeholders' => ['thesis', 'course_name'],
                'expected_output' => 'A labelled outline with a research prompt under each section.',
                'integrity_note' => 'An outline is a scaffold; write the essay in your own words and cite sources.',
                'provenance' => self::IN_HOUSE,
                'related_tools' => ['Google Docs'],
            ],
            [
                'category' => 'academic-writing',
                'title' => 'Critique My Argument',
                'purpose' => 'Find the weakest link in an argument you have already written.',
                'template_body' => 'Here is my argument for {{claim}}: {{argument}}. Identify the weakest step, the strongest counter-argument, and any evidence I have asserted without support. Do not rewrite my text.',
                'placeholders' => ['claim', 'argument'],
                'expected_output' => 'A list of specific weaknesses with the counter-argument stated plainly, and no rewritten prose.',
                'integrity_note' => 'Ask for critique, not replacement text. Fixing the weakness in your own words is the work.',
                'provenance' => self::IN_HOUSE,
                'related_tools' => ['Google Docs', 'Hemingway Editor'],
            ],
            [
                'category' => 'academic-writing',
                'title' => 'Tighten a Paragraph I Wrote',
                'purpose' => 'Get concrete editing feedback without handing over authorship.',
                'template_body' => 'This paragraph is {{word_count}} words and needs to be shorter without losing meaning: {{paragraph}}. Point out redundancy, hedging, and unclear referents as a list of specific edits I can make myself.',
                'placeholders' => ['word_count', 'paragraph'],
                'expected_output' => 'A list of specific, located edits rather than a rewritten paragraph.',
                'integrity_note' => 'Apply the edits yourself. Accepting a wholesale rewrite means the prose is no longer yours.',
                'provenance' => self::IN_HOUSE,
                'related_tools' => ['Hemingway Editor', 'LanguageTool'],
            ],
            [
                'category' => 'research-and-sources',
                'title' => 'Turn a Topic Into Search Queries',
                'purpose' => 'Convert a broad topic into precise database search strings.',
                'template_body' => 'My research question is {{research_question}} in the field of {{field}}. Produce six search queries with different vocabulary and synonyms, including boolean operators, and say what each query is designed to find.',
                'placeholders' => ['research_question', 'field'],
                'expected_output' => 'Six distinct search strings, each with a one-line explanation of its intent.',
                'integrity_note' => 'The queries are a starting point. Run them yourself and read what you find before citing it.',
                'provenance' => self::IN_HOUSE,
                'related_tools' => ['Google Scholar', 'Semantic Scholar'],
            ],
            [
                'category' => 'research-and-sources',
                'title' => 'Interrogate a Source Before Citing It',
                'purpose' => 'Apply a consistent credibility check to a source you are considering.',
                'template_body' => 'I am considering citing this source for the claim {{claim}}: {{source_details}}. List the questions I should answer about its method, sample, funding, date, and peer-review status before I rely on it.',
                'placeholders' => ['claim', 'source_details'],
                'expected_output' => 'A checklist of specific questions to answer from the source itself.',
                'integrity_note' => 'Answer the questions by reading the source. Never cite a paper you have not opened.',
                'provenance' => self::IN_HOUSE,
                'related_tools' => ['Semantic Scholar', 'Consensus'],
            ],
            [
                'category' => 'research-and-sources',
                'title' => 'Find the Gap in a Literature Set',
                'purpose' => 'Identify what a set of papers you have read does not cover.',
                'template_body' => 'I have read these sources on {{topic}}: {{source_summaries}}. Based only on what I have described, what questions remain unanswered, and what would I need to check before claiming a gap exists?',
                'placeholders' => ['topic', 'source_summaries'],
                'expected_output' => 'Candidate gaps, each paired with what you must verify before asserting it.',
                'integrity_note' => 'A gap claimed without a systematic search is a guess. Verify before it reaches your write-up.',
                'provenance' => self::IN_HOUSE,
                'related_tools' => ['Connected Papers', 'Elicit'],
            ],
            [
                'category' => 'citation-and-referencing',
                'title' => 'Check a Reference List for Consistency',
                'purpose' => 'Catch formatting inconsistencies across a finished reference list.',
                'template_body' => 'Check this reference list against {{citation_style}} and list every inconsistency you can see, with the entry number: {{reference_list}}. Do not invent missing details.',
                'placeholders' => ['citation_style', 'reference_list'],
                'expected_output' => 'A numbered list of specific formatting problems, with nothing invented to fill gaps.',
                'integrity_note' => 'Fix each entry against the real source. A fabricated citation is a serious integrity breach.',
                'provenance' => self::IN_HOUSE,
                'related_tools' => ['Zotero', 'Purdue OWL'],
            ],
            [
                'category' => 'citation-and-referencing',
                'title' => 'Decide What Needs a Citation',
                'purpose' => 'Distinguish common knowledge from claims that require attribution.',
                'template_body' => 'For this passage from my draft in the field of {{field}}: {{passage}}, list every statement that needs a citation and say why. Flag anything that reads as though it came from a source I have not named.',
                'placeholders' => ['field', 'passage'],
                'expected_output' => 'A statement-by-statement list marking which need attribution and why.',
                'integrity_note' => 'When in doubt, cite. Unattributed borrowed ideas are plagiarism even when the wording is your own.',
                'provenance' => self::IN_HOUSE,
                'related_tools' => ['Purdue OWL', 'Zotero'],
            ],
            [
                'category' => 'exam-preparation',
                'title' => 'Generate Practice Questions From My Notes',
                'purpose' => 'Convert your own notes into retrieval practice.',
                'template_body' => 'From these notes on {{topic}}: {{notes}}, write {{question_count}} practice questions at increasing difficulty. Give the answers separately at the end so I can attempt them first.',
                'placeholders' => ['topic', 'notes', 'question_count'],
                'expected_output' => 'A numbered question set with a separate answer key.',
                'integrity_note' => 'These are practice questions for you, never a source of answers during an assessment.',
                'provenance' => self::IN_HOUSE,
                'related_tools' => ['Anki', 'Quizlet'],
            ],
            [
                'category' => 'exam-preparation',
                'title' => 'Diagnose Why I Got This Wrong',
                'purpose' => 'Turn a wrong answer into a specific, fixable misunderstanding.',
                'template_body' => 'The question was {{question}}. My answer was {{my_answer}} and the correct answer is {{correct_answer}}. Explain where my reasoning diverged and give me one similar problem to try unaided.',
                'placeholders' => ['question', 'my_answer', 'correct_answer'],
                'expected_output' => 'A diagnosis of the specific reasoning error plus one fresh practice problem.',
                'integrity_note' => 'Use this after an attempt, on practice material only. Do not use it during an assessment.',
                'provenance' => self::IN_HOUSE,
                'related_tools' => ['Khan Academy', 'Symbolab'],
            ],
            [
                'category' => 'exam-preparation',
                'title' => 'Build a Revision Schedule From a Syllabus',
                'purpose' => 'Spread revision across topics with spacing rather than cramming.',
                'template_body' => 'The exam is on {{exam_date}} and covers {{topics}}. I rate my confidence as {{confidence_notes}}. Build a spaced revision schedule that revisits weak topics more often and includes at least two full past-paper attempts.',
                'placeholders' => ['exam_date', 'topics', 'confidence_notes'],
                'expected_output' => 'A dated schedule with repeat visits to weak topics and scheduled past-paper attempts.',
                'integrity_note' => 'Rate your confidence honestly. A flattering self-assessment produces a schedule that fails you.',
                'provenance' => self::IN_HOUSE,
                'related_tools' => ['Anki', 'Google Calendar'],
            ],
            [
                'category' => 'note-taking',
                'title' => 'Turn Messy Lecture Notes Into Structure',
                'purpose' => 'Reorganise rough notes without losing your own wording.',
                'template_body' => 'These are my rough notes from a lecture on {{topic}}: {{notes}}. Reorganise them under headings, mark anything that looks incomplete, and list the questions I should ask to fill the gaps. Keep my wording.',
                'placeholders' => ['topic', 'notes'],
                'expected_output' => 'The same content under clear headings, with gaps flagged and follow-up questions listed.',
                'integrity_note' => 'These are your notes. Restructuring them is fine; replacing them with generated content is not studying.',
                'provenance' => self::IN_HOUSE,
                'related_tools' => ['Obsidian', 'Microsoft OneNote'],
            ],
            [
                'category' => 'note-taking',
                'title' => 'Explain It Back to Check Understanding',
                'purpose' => 'Test comprehension by explaining a concept and having the explanation challenged.',
                'template_body' => 'Here is my explanation of {{concept}} in my own words: {{my_explanation}}. Point out what is wrong, what is missing, and what I have oversimplified. Do not give me a replacement explanation.',
                'placeholders' => ['concept', 'my_explanation'],
                'expected_output' => 'Specific corrections and omissions, with no substitute explanation supplied.',
                'integrity_note' => 'The point is to expose the gaps in your understanding, so ask for critique rather than a better explanation.',
                'provenance' => self::IN_HOUSE,
                'related_tools' => ['RemNote', 'Logseq'],
            ],
            [
                'category' => 'math-and-quantitative',
                'title' => 'Find My Error Without Giving the Answer',
                'purpose' => 'Locate a mistake in your own working while preserving the learning.',
                'template_body' => 'Here is my working for this problem: {{problem}}. My steps: {{my_working}}. Tell me the first line where I went wrong and what concept I have misapplied. Do not give me the correct final answer.',
                'placeholders' => ['problem', 'my_working'],
                'expected_output' => 'The first incorrect line identified and the underlying concept named, with no final answer.',
                'integrity_note' => 'Withholding the answer is deliberate. Rework the problem yourself from the identified line.',
                'provenance' => self::IN_HOUSE,
                'related_tools' => ['Wolfram Alpha', 'Symbolab'],
            ],
            [
                'category' => 'math-and-quantitative',
                'title' => 'Interpret a Result in Context',
                'purpose' => 'Move from a computed number to a defensible interpretation.',
                'template_body' => 'I computed {{result}} for {{analysis_description}} with a sample of {{sample_details}}. What does this actually support, what does it not support, and what assumptions am I relying on?',
                'placeholders' => ['result', 'analysis_description', 'sample_details'],
                'expected_output' => 'A clear separation of supported claims, unsupported claims, and underlying assumptions.',
                'integrity_note' => 'Report what your data supports, including when the honest answer is that it supports very little.',
                'provenance' => self::IN_HOUSE,
                'related_tools' => ['Google Colab', 'Desmos Graphing Calculator'],
            ],
            [
                'category' => 'coding-and-cs',
                'title' => 'Explain This Error Message',
                'purpose' => 'Understand a failure rather than pasting a fix.',
                'template_body' => 'I am getting this error in {{language}}: {{error_message}}. Here is the relevant code: {{code}}. Explain what the error means and what class of mistake causes it. Do not write the fixed code for me.',
                'placeholders' => ['language', 'error_message', 'code'],
                'expected_output' => 'A plain explanation of the error and its typical causes, with no rewritten code.',
                'integrity_note' => 'Check your course policy on AI assistance before using this on assessed work, and write the fix yourself.',
                'provenance' => self::IN_HOUSE,
                'related_tools' => ['Visual Studio Code', 'Exercism'],
            ],
            [
                'category' => 'presentations-and-visuals',
                'title' => 'Structure a Presentation Argument',
                'purpose' => 'Decide what the talk argues before any slide exists.',
                'template_body' => 'I have {{minutes}} minutes to present {{topic}} to {{audience}}. My main claim is {{claim}}. Propose a structure with a time budget per section and tell me what to cut if I overrun.',
                'placeholders' => ['minutes', 'topic', 'audience', 'claim'],
                'expected_output' => 'A timed section structure with an explicit cut list.',
                'integrity_note' => 'The structure is a scaffold; the argument and the delivery are yours.',
                'provenance' => self::IN_HOUSE,
                'related_tools' => ['Google Slides', 'Canva'],
            ],
            [
                'category' => 'accessibility-and-inclusion',
                'title' => 'Write Alternative Text for a Figure',
                'purpose' => 'Describe a figure so a screen-reader user gets the same information.',
                'template_body' => 'My figure shows {{figure_description}} and appears in a document about {{context}}. The point it makes is {{key_point}}. Draft concise alternative text and a longer description, and tell me what a reader would still miss.',
                'placeholders' => ['figure_description', 'context', 'key_point'],
                'expected_output' => 'Short alternative text, a longer description, and an honest note on what is still lost.',
                'integrity_note' => 'Describe your own figure accurately. Alternative text that overstates what the figure shows misleads the reader who relies on it most.',
                'provenance' => self::IN_HOUSE,
                'related_tools' => ['WAVE Web Accessibility Evaluation Tool', 'NVDA Screen Reader'],
            ],
        ];
    }

    /**
     * @return list<array{category: string, title: string, goal: string, expected_outcome: string, integrity_note: string, provenance: string, steps: list<array{title: string, instruction: string, destination_action: string|null}>}>
     */
    public static function workflows(): array
    {
        return [
            [
                'category' => 'study-planning',
                'title' => 'From Reading to Revision Notes',
                'goal' => 'Convert a chapter into revision notes with integrity.',
                'expected_outcome' => 'A set of notes in your own words with sources recorded.',
                'integrity_note' => 'Every step keeps the original source attributed and reviewed.',
                'provenance' => self::IN_HOUSE,
                'steps' => [
                    ['title' => 'Read and highlight', 'instruction' => 'Skim the chapter, then mark the load-bearing claims.', 'destination_action' => WorkflowDestinationAction::SaveResource->value],
                    ['title' => 'Summarize each section', 'instruction' => 'Write one sentence per section in your own words.', 'destination_action' => null],
                    ['title' => 'Review and cite', 'instruction' => 'Check each note against the source and record the citation.', 'destination_action' => WorkflowDestinationAction::SaveResource->value],
                ],
            ],
            [
                'category' => 'study-planning',
                'title' => 'Plan a Week You Can Actually Keep',
                'goal' => 'Produce a weekly plan sized to the hours you really have.',
                'expected_outcome' => 'A dated week plan with an explicit list of what did not fit.',
                'integrity_note' => 'If the work does not fit the week, raise it with your tutor rather than quietly dropping it.',
                'provenance' => self::IN_HOUSE,
                'steps' => [
                    ['title' => 'List every deadline', 'instruction' => 'Write down every fixed commitment and deadline for the next two weeks before planning anything.', 'destination_action' => WorkflowDestinationAction::CreateTask->value],
                    ['title' => 'Measure your real hours', 'instruction' => 'Subtract classes, work, travel, and sleep from the week and write down the study hours that are genuinely left.', 'destination_action' => null],
                    ['title' => 'Block and flag', 'instruction' => 'Allocate the hours to deadlines in priority order, then write down explicitly what did not fit.', 'destination_action' => WorkflowDestinationAction::CreateTask->value],
                ],
            ],
            [
                'category' => 'academic-writing',
                'title' => 'Essay From Blank Page to Submission',
                'goal' => 'Move an essay through outline, draft, and revision without losing your own voice.',
                'expected_outcome' => 'A submitted essay written by you, with a revision history that shows the process.',
                'integrity_note' => 'Assistance is allowed on structure and correctness. The argument and the prose must be yours, and any permitted AI assistance must be declared if your brief requires it.',
                'provenance' => self::IN_HOUSE,
                'steps' => [
                    ['title' => 'Write the thesis in one sentence', 'instruction' => 'State the claim the essay defends in a single sentence. If you cannot, you are not ready to outline.', 'destination_action' => null],
                    ['title' => 'Outline and gather evidence', 'instruction' => 'Build a section outline and attach at least one source to each section before drafting.', 'destination_action' => WorkflowDestinationAction::UsePrompt->value],
                    ['title' => 'Draft, then revise against critique', 'instruction' => 'Draft in one pass without editing, then revise using specific critique of the weakest step.', 'destination_action' => WorkflowDestinationAction::SaveResource->value],
                ],
            ],
            [
                'category' => 'research-and-sources',
                'title' => 'Screen a Literature Set Honestly',
                'goal' => 'Go from a broad topic to a screened, verified set of sources.',
                'expected_outcome' => 'A shortlist of sources you have actually read, each with a recorded reason for inclusion.',
                'integrity_note' => 'Never cite a source you have not opened, and never let an automated summary stand in for reading it.',
                'provenance' => self::IN_HOUSE,
                'steps' => [
                    ['title' => 'Search broadly', 'instruction' => 'Run several query variants with different vocabulary and record which query found each candidate.', 'destination_action' => WorkflowDestinationAction::UseTool->value],
                    ['title' => 'Screen on abstracts', 'instruction' => 'Reject or shortlist on the abstract alone and write one line of justification for each decision.', 'destination_action' => null],
                    ['title' => 'Read and verify', 'instruction' => 'Read every shortlisted paper in full and confirm each claim you intend to cite appears in the source.', 'destination_action' => WorkflowDestinationAction::SaveResource->value],
                ],
            ],
            [
                'category' => 'citation-and-referencing',
                'title' => 'Build a Reference List You Can Defend',
                'goal' => 'Produce a complete, consistent, verified reference list.',
                'expected_outcome' => 'A reference list where every entry matches a source you have opened.',
                'integrity_note' => 'A fabricated or unchecked citation is a serious integrity breach even when it is an honest mistake.',
                'provenance' => self::IN_HOUSE,
                'steps' => [
                    ['title' => 'Capture as you read', 'instruction' => 'Save each source to a reference manager at the moment you decide to use it, not at the end.', 'destination_action' => WorkflowDestinationAction::UseTool->value],
                    ['title' => 'Correct the metadata', 'instruction' => 'Check author, year, title, and venue on every captured record against the source itself.', 'destination_action' => null],
                    ['title' => 'Cross-check in-text against the list', 'instruction' => 'Confirm every in-text citation appears in the list and every list entry is cited in the text.', 'destination_action' => WorkflowDestinationAction::UseTemplate->value],
                ],
            ],
            [
                'category' => 'exam-preparation',
                'title' => 'Two Weeks to an Exam',
                'goal' => 'Convert a syllabus and honest self-assessment into spaced revision.',
                'expected_outcome' => 'A spaced schedule, a worked past paper, and a shortlist of remaining weak topics.',
                'integrity_note' => 'Practice material only. Nothing in this workflow is for use during an assessment.',
                'provenance' => self::IN_HOUSE,
                'steps' => [
                    ['title' => 'Rate every topic honestly', 'instruction' => 'List each syllabus topic and rate your confidence. Generous ratings produce a schedule that fails you.', 'destination_action' => null],
                    ['title' => 'Build spaced practice', 'instruction' => 'Schedule weak topics more often and convert key facts into your own retrieval practice cards.', 'destination_action' => WorkflowDestinationAction::UseTool->value],
                    ['title' => 'Sit a full past paper', 'instruction' => 'Do one complete past paper under timed conditions, then diagnose every error before revising further.', 'destination_action' => WorkflowDestinationAction::CreateTask->value],
                ],
            ],
            [
                'category' => 'note-taking',
                'title' => 'Lecture to Long-Term Memory',
                'goal' => 'Take a lecture from live capture to notes you will still understand in June.',
                'expected_outcome' => 'Structured notes in your own words with open questions recorded.',
                'integrity_note' => 'Confirm recording is permitted before you record any lecture or seminar.',
                'provenance' => self::IN_HOUSE,
                'steps' => [
                    ['title' => 'Capture roughly, live', 'instruction' => 'Capture keywords and questions during the lecture rather than trying to transcribe it.', 'destination_action' => null],
                    ['title' => 'Restructure within a day', 'instruction' => 'Within twenty-four hours, reorganise the rough capture under headings and mark what you did not follow.', 'destination_action' => WorkflowDestinationAction::UsePrompt->value],
                    ['title' => 'Close the gaps', 'instruction' => 'Answer your own open questions from the reading or in office hours, and record the answers with the notes.', 'destination_action' => WorkflowDestinationAction::SaveResource->value],
                ],
            ],
            [
                'category' => 'math-and-quantitative',
                'title' => 'Work a Problem Set Properly',
                'goal' => 'Get through a problem set in a way that survives the exam.',
                'expected_outcome' => 'Completed problems plus a written record of every mistake and its cause.',
                'integrity_note' => 'Check answers only after your own attempt, and never submit worked steps generated by a solver as your own.',
                'provenance' => self::IN_HOUSE,
                'steps' => [
                    ['title' => 'Attempt unaided', 'instruction' => 'Work every problem to a final answer without help, marking where you got stuck.', 'destination_action' => null],
                    ['title' => 'Verify, do not copy', 'instruction' => 'Check your answers with a computational tool. Where they differ, find your own error rather than taking the output.', 'destination_action' => WorkflowDestinationAction::UseTool->value],
                    ['title' => 'Record the error pattern', 'instruction' => 'Write one line per mistake naming the concept you misapplied, and revisit that list before the exam.', 'destination_action' => WorkflowDestinationAction::SaveResource->value],
                ],
            ],
            [
                'category' => 'coding-and-cs',
                'title' => 'Debug Without Outsourcing the Thinking',
                'goal' => 'Find and fix a bug in a way that teaches you the underlying cause.',
                'expected_outcome' => 'A fixed program and a written explanation of the root cause.',
                'integrity_note' => 'Check your module policy on AI coding assistance before using any generator on assessed work, and never submit code you cannot explain line by line.',
                'provenance' => self::IN_HOUSE,
                'steps' => [
                    ['title' => 'Reproduce reliably', 'instruction' => 'Reduce the failure to the smallest input that still triggers it before changing any code.', 'destination_action' => null],
                    ['title' => 'Inspect real state', 'instruction' => 'Step through with a debugger and compare what the program actually does against what you expected.', 'destination_action' => WorkflowDestinationAction::UseTool->value],
                    ['title' => 'Fix and explain', 'instruction' => 'Write the fix yourself and record a one-paragraph explanation of the root cause in your notes.', 'destination_action' => WorkflowDestinationAction::SaveResource->value],
                ],
            ],
            [
                'category' => 'presentations-and-visuals',
                'title' => 'Build a Talk That Argues Something',
                'goal' => 'Go from a topic to a rehearsed talk with a clear claim.',
                'expected_outcome' => 'A timed deck with speaker notes and at least one full rehearsal completed.',
                'integrity_note' => 'Credit every figure, dataset, and image you did not create, on the slide where it appears.',
                'provenance' => self::IN_HOUSE,
                'steps' => [
                    ['title' => 'Write the claim and the notes first', 'instruction' => 'Write the single claim and the speaker notes before opening any slide software.', 'destination_action' => WorkflowDestinationAction::UsePrompt->value],
                    ['title' => 'Build minimal slides', 'instruction' => 'Build one slide per beat, carrying only what the audience cannot get from your voice, and attribute every borrowed figure.', 'destination_action' => WorkflowDestinationAction::UseTemplate->value],
                    ['title' => 'Rehearse against the clock', 'instruction' => 'Rehearse aloud once end to end, then cut whatever pushed you over time.', 'destination_action' => WorkflowDestinationAction::CreateTask->value],
                ],
            ],
        ];
    }

    /**
     * @return list<array{category: string, title: string, summary: string, integrity_note: string, provenance: string, body: string}>
     */
    public static function templates(): array
    {
        return [
            [
                'category' => 'study-planning',
                'title' => 'Weekly Study Plan',
                'summary' => 'A reusable weekly grid you copy each week to timebox study across your courses before deadlines.',
                'integrity_note' => 'Fill the plan in yourself and treat it as a schedule, not as completed work; do the studying it describes.',
                'provenance' => self::IN_HOUSE,
                'body' => <<<'MD'
                    # Weekly Study Plan

                    **Week of:** _{{week_start}}_

                    ## Goals for the week
                    - [ ] {{goal_1}}
                    - [ ] {{goal_2}}
                    - [ ] {{goal_3}}

                    ## Time blocks
                    | Day | Course / topic | Focus block | Notes |
                    | --- | -------------- | ----------- | ----- |
                    | Mon | {{course}} | 25 min x 2 | |
                    | Tue | {{course}} | 25 min x 2 | |
                    | Wed | {{course}} | 25 min x 2 | |
                    | Thu | {{course}} | 25 min x 2 | |
                    | Fri | {{course}} | 25 min x 2 | |

                    ## End-of-week review
                    - What went to plan?
                    - What slipped, and why?
                    - One adjustment for next week: _{{adjustment}}_
                    MD,
            ],
            [
                'category' => 'study-planning',
                'title' => 'Deadline Countdown Sheet',
                'summary' => 'A backwards plan from a single deadline, breaking a large submission into dated checkpoints.',
                'integrity_note' => 'Record real completion dates. A countdown sheet you backfill afterwards teaches you nothing about your own pace.',
                'provenance' => self::IN_HOUSE,
                'body' => <<<'MD'
                    # Deadline Countdown: {{assignment_title}}

                    **Due:** {{due_date}}  **Weight:** {{weight}}

                    ## Working backwards
                    | Checkpoint | Target date | Done on | Notes |
                    | ---------- | ----------- | ------- | ----- |
                    | Brief understood, questions asked | | | |
                    | Sources gathered | | | |
                    | Outline agreed | | | |
                    | First full draft | | | |
                    | Revision pass | | | |
                    | References checked | | | |
                    | Submitted | | | |

                    ## Risks
                    - What could delay this? _{{risk}}_
                    - Who do I ask if it does? _{{contact}}_
                    MD,
            ],
            [
                'category' => 'academic-writing',
                'title' => 'Literature Review Outline',
                'summary' => 'A section-by-section scaffold for organising sources into a literature review you then write in your own words.',
                'integrity_note' => 'Use the outline to structure your own analysis and cite every source; do not present summarised sources as your own findings.',
                'provenance' => self::IN_HOUSE,
                'body' => <<<'MD'
                    # Literature Review Outline

                    **Topic / question:** _{{research_question}}_

                    ## 1. Introduction
                    - Scope of the review
                    - Why this topic matters

                    ## 2. Themes
                    ### Theme A: {{theme_a}}
                    - Source 1: key claim, method, limitation
                    - Source 2: key claim, method, limitation
                    - Where they agree / disagree

                    ### Theme B: {{theme_b}}
                    - Source 1: key claim, method, limitation
                    - Source 2: key claim, method, limitation

                    ## 3. Gaps and open questions
                    - What is missing across the sources?

                    ## 4. Synthesis
                    - How the themes connect to your question

                    ## References
                    1. {{citation_1}}
                    2. {{citation_2}}
                    MD,
            ],
            [
                'category' => 'academic-writing',
                'title' => 'Essay Planning Sheet',
                'summary' => 'A one-page plan that forces a single-sentence thesis and an evidence source for every section before drafting.',
                'integrity_note' => 'Every section needs a source you have actually read. An empty evidence column is a warning, not a formatting gap.',
                'provenance' => self::IN_HOUSE,
                'body' => <<<'MD'
                    # Essay Plan: {{essay_title}}

                    **Module:** {{course}}  **Word limit:** {{word_limit}}  **Due:** {{due_date}}

                    ## Thesis in one sentence
                    _{{thesis}}_

                    ## Sections
                    | # | Section | Point it makes | Evidence (source you have read) | Words |
                    | - | ------- | -------------- | ------------------------------- | ----- |
                    | 1 | Introduction | | | |
                    | 2 | | | | |
                    | 3 | | | | |
                    | 4 | | | | |
                    | 5 | Conclusion | | | |

                    ## Counter-argument I must address
                    _{{counter_argument}}_

                    ## Declaration check
                    - [ ] I have checked the brief for what assistance is permitted
                    - [ ] Any permitted assistance is declared as the brief requires
                    MD,
            ],
            [
                'category' => 'academic-writing',
                'title' => 'Lab Report Skeleton',
                'summary' => 'A standard lab-report structure you copy per experiment so every write-up covers method, results, and analysis consistently.',
                'integrity_note' => 'Record your own observations and analysis; copying results you did not measure is an integrity violation.',
                'provenance' => self::IN_HOUSE,
                'body' => <<<'MD'
                    # Lab Report: {{experiment_title}}

                    **Course:** {{course}}  **Date:** {{date}}

                    ## Aim
                    State the objective of the experiment in one sentence.

                    ## Hypothesis
                    _{{hypothesis}}_

                    ## Materials and method
                    1. Step one
                    2. Step two
                    3. Step three

                    ## Results
                    | Trial | Measurement | Units |
                    | ----- | ----------- | ----- |
                    | 1 | | |
                    | 2 | | |
                    | 3 | | |

                    ## Analysis
                    - What do the results show?
                    - Sources of error and their likely effect

                    ## Conclusion
                    Relate the results back to the aim and hypothesis.
                    MD,
            ],
            [
                'category' => 'research-and-sources',
                'title' => 'Source Evaluation Sheet',
                'summary' => 'A per-source record of method, sample, funding, and relevance so credibility judgements are consistent and traceable.',
                'integrity_note' => 'Complete this from the source itself. A sheet filled in from an abstract or an automated summary is not evidence that you read the paper.',
                'provenance' => self::IN_HOUSE,
                'body' => <<<'MD'
                    # Source Evaluation: {{source_title}}

                    **Authors:** {{authors}}  **Year:** {{year}}  **Venue:** {{venue}}

                    ## What it claims
                    - Main finding:
                    - Claim I want to use it for:

                    ## How it was done
                    - Method:
                    - Sample or dataset:
                    - Stated limitations:

                    ## Credibility checks
                    - [ ] Peer reviewed
                    - [ ] Funding and conflicts of interest stated
                    - [ ] Method appropriate to the claim
                    - [ ] Date still current for this field
                    - [ ] I have read the full text, not only the abstract

                    ## Verdict
                    - Use / do not use, and why: _{{verdict}}_
                    MD,
            ],
            [
                'category' => 'research-and-sources',
                'title' => 'Research Question Refinement Sheet',
                'summary' => 'A worksheet that narrows a broad interest into a researchable question with a stated scope and method.',
                'integrity_note' => 'Be honest about scope. A question you cannot answer with the time and access you have is a problem to raise early, not to hide.',
                'provenance' => self::IN_HOUSE,
                'body' => <<<'MD'
                    # Research Question Refinement

                    ## Starting interest
                    _{{broad_topic}}_

                    ## Narrowing
                    | Dimension | My choice | Why |
                    | --------- | --------- | --- |
                    | Population or context | | |
                    | Time period | | |
                    | Outcome or variable | | |
                    | Discipline or lens | | |

                    ## Candidate question
                    _{{candidate_question}}_

                    ## Feasibility check
                    - Data or sources I can actually access:
                    - Time available:
                    - Skills or methods I still need:
                    - Ethical approval needed? _{{ethics}}_

                    ## Final question
                    _{{final_question}}_
                    MD,
            ],
            [
                'category' => 'citation-and-referencing',
                'title' => 'Citation Checklist',
                'summary' => 'A pre-submission pass over every citation and reference so nothing is missing, malformed, or unverified.',
                'integrity_note' => 'Every entry must correspond to a source you opened. Fabricated or unchecked references are a serious integrity breach.',
                'provenance' => self::IN_HOUSE,
                'body' => <<<'MD'
                    # Citation Checklist: {{document_title}}

                    **Required style:** {{citation_style}}

                    ## Per source
                    | # | Source | In text? | In list? | Metadata verified? | Page refs for quotes? |
                    | - | ------ | -------- | -------- | ------------------ | --------------------- |
                    | 1 | | | | | |
                    | 2 | | | | | |
                    | 3 | | | | | |

                    ## Whole-document checks
                    - [ ] Every in-text citation appears in the reference list
                    - [ ] Every reference-list entry is cited in the text
                    - [ ] Reference list ordered as the style requires
                    - [ ] Author, year, title, and venue verified against each source
                    - [ ] Every direct quote has a page or locator reference
                    - [ ] No entry was generated automatically without being checked
                    MD,
            ],
            [
                'category' => 'exam-preparation',
                'title' => 'Past Paper Debrief',
                'summary' => 'A structured review of a completed past paper that turns each lost mark into a named, fixable cause.',
                'integrity_note' => 'Sit the paper under real timed conditions first. A debrief on a paper you worked through with notes open measures nothing.',
                'provenance' => self::IN_HOUSE,
                'body' => <<<'MD'
                    # Past Paper Debrief: {{paper_reference}}

                    **Sat on:** {{date}}  **Time taken:** {{time_taken}}  **Conditions:** timed / open book

                    ## Marks lost
                    | Q | Marks lost | Cause: knowledge / method / misread / time | Topic to revisit |
                    | - | ---------- | ------------------------------------------ | ---------------- |
                    | | | | |
                    | | | | |
                    | | | | |

                    ## Patterns
                    - Most common cause:
                    - Topics appearing more than once:

                    ## Next three actions
                    1. {{action_1}}
                    2. {{action_2}}
                    3. {{action_3}}
                    MD,
            ],
            [
                'category' => 'note-taking',
                'title' => 'Cornell Lecture Notes',
                'summary' => 'The Cornell layout with a cue column, note body, and summary, sized for a single lecture.',
                'integrity_note' => 'Write the summary from memory before checking your notes. Copying it back from the body defeats the method.',
                'provenance' => self::IN_HOUSE,
                'body' => <<<'MD'
                    # {{lecture_title}}

                    **Course:** {{course}}  **Date:** {{date}}

                    | Cues and questions | Notes |
                    | ------------------ | ----- |
                    | {{cue_1}} | |
                    | {{cue_2}} | |
                    | {{cue_3}} | |

                    ## Things I did not follow
                    - {{gap_1}}
                    - {{gap_2}}

                    ## Summary in my own words
                    _Write this from memory before rereading the notes._

                    {{summary}}
                    MD,
            ],
            [
                'category' => 'presentations-and-visuals',
                'title' => 'Presentation Storyboard',
                'summary' => 'A slide-by-slide storyboard with time budget and speaker notes, filled in before any slide software is opened.',
                'integrity_note' => 'Credit every borrowed figure, dataset, or image on the slide where it appears.',
                'provenance' => self::IN_HOUSE,
                'body' => <<<'MD'
                    # Storyboard: {{talk_title}}

                    **Audience:** {{audience}}  **Time:** {{minutes}} minutes

                    ## The one claim
                    _{{claim}}_

                    ## Slides
                    | # | Slide title | What I say | What is on screen | Source to credit | Time |
                    | - | ----------- | ---------- | ----------------- | ---------------- | ---- |
                    | 1 | | | | | |
                    | 2 | | | | | |
                    | 3 | | | | | |
                    | 4 | | | | | |
                    | 5 | | | | | |

                    ## Cut list if I overrun
                    1. {{cut_1}}
                    2. {{cut_2}}

                    ## Likely questions
                    - {{question_1}}
                    - {{question_2}}
                    MD,
            ],
            [
                'category' => 'accessibility-and-inclusion',
                'title' => 'Accessible Document Checklist',
                'summary' => 'A pre-submission accessibility pass covering headings, alternative text, contrast, links, and tables.',
                'integrity_note' => 'Describe your own figures accurately. Alternative text that overstates what a figure shows misleads the readers who depend on it most.',
                'provenance' => self::IN_HOUSE,
                'body' => <<<'MD'
                    # Accessible Document Checklist: {{document_title}}

                    ## Structure
                    - [ ] Real heading styles used, in order, with no skipped levels
                    - [ ] Lists use real list formatting rather than manual dashes
                    - [ ] Reading order is correct when navigated by keyboard

                    ## Images and figures
                    - [ ] Every informative image has alternative text
                    - [ ] Decorative images are marked as decorative
                    - [ ] Charts state their finding in the caption, not only in colour

                    ## Colour and contrast
                    - [ ] Text contrast meets the required ratio
                    - [ ] No information is carried by colour alone

                    ## Links and tables
                    - [ ] Link text describes the destination rather than saying "click here"
                    - [ ] Tables have header rows and no merged cells used for layout

                    ## Final pass
                    - [ ] Checked with a screen reader or an accessibility checker
                    - [ ] Exported format preserves the tags (checked, not assumed)
                    MD,
            ],
        ];
    }
}
