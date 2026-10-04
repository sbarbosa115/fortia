import {Fragment} from 'react';
import {highlightParts} from '../model/questionnaireFilter';

/** The text with the words of the search marked (<mark>), matched without case or accents. */
export function Highlight({text, search}: {text: string; search: string}) {
  return (
    <>
      {highlightParts(text, search).map((part, index) =>
        part.match ? (
          <mark key={index} className="asg-wiz__mark">
            {part.text}
          </mark>
        ) : (
          <Fragment key={index}>{part.text}</Fragment>
        ),
      )}
    </>
  );
}
