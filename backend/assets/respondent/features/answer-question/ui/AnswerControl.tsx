import type {ControlProps} from '../model/types';
import {CheckboxControl, RadioControl, SelectControl} from './ChoiceControls';
import {ContactControl, RangeControl, TextControl} from './InputControls';
import {RankingControl} from './RankingControl';
import {
  GenderControl,
  HeightControl,
  JeansSizeControl,
  WeightCompositeControl,
  WeightControl,
} from './Themes';

/**
 * The answer control of a question (PRD §9.4) or of its special theme (§9.5). Audio and file answers are their own
 * features; a message renders nothing here.
 */
export function AnswerControl(props: ControlProps) {
  switch (props.question.theme_name) {
    case 'gender':
      return <GenderControl {...props} />;
    case 'weight':
      return <WeightControl {...props} />;
    case 'height':
      return <HeightControl {...props} />;
    case 'weight-composite':
      return <WeightCompositeControl {...props} />;
    case 'jeans-size':
      return <JeansSizeControl {...props} />;
    default:
      break;
  }
  switch (props.control.type) {
    case 'radio':
      return <RadioControl {...props} />;
    case 'checkbox':
      return <CheckboxControl {...props} />;
    case 'select':
      return <SelectControl {...props} />;
    case 'range':
      return <RangeControl {...props} />;
    case 'text':
      return <TextControl {...props} />;
    case 'email':
    case 'tel':
    case 'phone':
      return <ContactControl {...props} />;
    case 'ranking':
      return <RankingControl {...props} />;
    default:
      return null;
  }
}
