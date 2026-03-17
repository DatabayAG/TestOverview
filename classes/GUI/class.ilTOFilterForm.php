<?php

class ilTOFilterForm
{
    /**
     * Render the horizontal filter form
     * @param array $membership_options Array of [ref_id => title]
     * @param string $participant Vorbelegung Teilnehmer
     * @param string $selected_membership Vorbelegung Mitgliedschaft
     * @param bool $own_results Vorbelegung Checkbox
     * @param string $form_action Die Action-URL für das Formular
     * @return string HTML
     */
    public function render(array $membership_options = [], $participant = '', $selected_membership = '', $own_results = false, $form_action = '')
    {
        $tpl_path = __DIR__ . '/../../templates/tpl.to_filter_form.html';
        $tpl = file_get_contents($tpl_path);

        // Membership options
        $options_html = '';
        foreach ($membership_options as $ref_id => $title) {
            $selected = ($ref_id == $selected_membership) ? 'selected' : '';
            $options_html .= '<option value="' . htmlspecialchars($ref_id) . '" ' . $selected . '>' . htmlspecialchars($title) . '</option>';
        }

        $tpl = str_replace('{MEMBERSHIP_OPTIONS}', $options_html, $tpl);
        $tpl = str_replace('{PARTICIPANT}', htmlspecialchars($participant), $tpl);
        $tpl = str_replace('{OWN_RESULTS_CHECKED}', $own_results ? 'checked' : '', $tpl);
        $tpl = str_replace('{FORM_ACTION}', $form_action ?? '', $tpl);

        $tpl = str_replace('{TXT_PARTICIPANT}', $this->txt('participant'), $tpl);
        $tpl = str_replace('{TXT_MEMBERSHIP}', $this->txt('membership'), $tpl);
        $tpl = str_replace('{TXT_SELECT}', $this->txt('select'), $tpl);
        $tpl = str_replace('{TXT_OWN_RESULTS}', $this->txt('own_results'), $tpl);
        $tpl = str_replace('{TXT_APPLY_FILTER}', $this->txt('apply_filter'), $tpl);
        $tpl = str_replace('{TXT_RESET_FILTER}', $this->txt('reset_filter'), $tpl);
        $tpl = str_replace('{TXT_HIDE_FILTER}', $this->txt('hide_filter'), $tpl);
        $tpl = str_replace('{TXT_SHOW_FILTER}', $this->txt('show_filter'), $tpl);

        return $tpl;
    }

    /**
     * Dummy-Übersetzungsfunktion (hier ersetzen durch ILIAS Sprachsystem)
     */
    protected function txt($id)
    {
        $map = [
            'participant' => 'Teilnehmer',
            'membership' => 'Mitgliedschaft',
            'select' => '-- Auswählen --',
            'own_results' => 'Nur eigene Ergebnisse',
            'apply_filter' => 'Filter anwenden',
            'reset_filter' => 'Filter zurücksetzen',
            'hide_filter' => 'Filter ausblenden',
            'show_filter' => 'Filter einblenden',
        ];
        return $map[$id] ?? $id;
    }
}
