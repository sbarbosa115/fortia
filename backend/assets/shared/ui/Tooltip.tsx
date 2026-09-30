import {type ReactNode, useId} from 'react';

/** A short explanation on hover and focus, announced to screen readers (aria-describedby). */
export function Tooltip({
  content,
  children,
}: {
  content: string;
  children: ReactNode;
}) {
  const id = useId();
  return (
    <span className="tooltip" aria-describedby={id} tabIndex={-1}>
      {children}
      <span role="tooltip" id={id} className="tooltip__bubble">
        {content}
      </span>
    </span>
  );
}
