<?php
/**
 * @license MIT
 * Full license text in LICENSE file
 */

/**
 * Delivers the CMS module
 *
 * @package Controller
 */
class Controller_CMS extends Controller_Core
{
    public function action_dashboard()
    {
        $this->layout = 'cms';
        $this->view = new View_Cms;
        $this->template = 'cms/dashboard';

        if ($this->request->method() === HTTP_Request::POST
            AND $this->request->post('submit') === 'Log in')
        {
            $config = Kohana::$config->load('cms');
            if ($this->request->post('password') === $config['password'])
            {
                Session::instance()->set('cms_logged_in', TRUE);
            }
            else
            {
                $this->view->error = TRUE;
            }
        }
        if ($this->request->method() === HTTP_Request::POST
            AND $this->request->post('submit') === 'Log out')
        {
            Session::instance()->delete('cms_logged_in');
            $this->redirect(Route::get('cms')->uri());
        }
    }

    /**
     * The editing page
     *
     * @return void
     */
    public function action_edit()
    {
        if ( ! (bool) Session::instance()->get('cms_logged_in', FALSE))
            return $this->redirect(Route::get('cms')->uri());

        // Define the view we are using
        $this->layout = 'cms';
        $this->template = 'cms/edit';

        // Are we editing a file?
        $template_path = $this->request->param('template_path');

        $template_file = Kohana::find_file('templates', $template_path, 'mustache');
        if ( ! empty($template_file))
        {
            $template_content = file_get_contents($template_file);
            $this->view->template_content = $template_content;
        }
        elseif ( ! empty($template_path))
            throw HTTP_Exception::factory(404, 'Template not found');

        if ($this->request->method() === HTTP_Request::POST)
        {
            $content_string = $this->request->post('content_string');

            // Check to make sure they haven't broken any existing mustache setups
            preg_match_all('/\{\{[#\/^]*?.*?\}\}/', $template_content, $current_mustaches);
            preg_match_all('/\{\{[#\/^]*?.*?\}\}/', $content_string, $proposed_mustaches);

            if ($current_mustaches >= $proposed_mustaches)
            {
                $tidy_config = array(
                    'doctype' => 'omit',
                    'drop-empty-paras' => TRUE,
                    'fix-backslash' => TRUE,
                    'fix-uri' => TRUE,
                    'break-before-br' => TRUE,
                    'show-body-only' => TRUE,
                    'logical-emphasis' => TRUE,
                    'indent' => TRUE,
                    'indent-spaces' => 4,
                    'vertical-space' => TRUE,
                    'new-blocklevel-tags' => 'article aside audio details figcaption figure footer header hgroup nav section source summary temp track video',
                    'new-empty-tags' => 'command embed keygen source track wbr',
                    'new-inline-tags' => 'audio canvas command datalist embed keygen mark meter output progress time video wbr',
                    'wrap' => 80
                );

                $tidy = new tidy;
                $tidy->parseString(utf8_decode($content_string), $tidy_config);
                $tidy->cleanRepair();
                $tidy_string = (string) $tidy;

                $tidy_string = str_replace('&nbsp;', '', $tidy_string);
                $tidy_string = str_replace('&#160;', '', $tidy_string);
                $tidy_string = preg_replace('/<[a-z]* style=".*">(\{\{[#\/^].*\}\})<\/[a-z]*>/i', '${1}', $tidy_string);
                $tidy_string = str_replace('"'.URL::base(), '"{{baseurl}}', $tidy_string);
                $tidy_string = str_replace('{{&gt;', '{{>', $tidy_string);

                file_put_contents($template_file, (string) $tidy_string);

                $this->view->template_content = $tidy_string;
            }
            else
            {
                $this->view->mustache_error = TRUE;
            }

        }
    }
}
