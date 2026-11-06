<?php

namespace app\commands;

use humhub\helpers\ThemeHelper;
use humhub\modules\user\models\ProfileField;
use humhub\modules\user\models\ProfileFieldCategory;
use humhub\modules\user\models\Profile;
use humhub\modules\user\models\User;
use humhub\modules\user\models\Group;
use humhub\modules\user\models\fieldtype\Select;
use humhub\modules\user\models\fieldtype\Text;
use humhub\modules\user\models\fieldtype\TextArea;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use humhub\modules\space\models\Space;
use humhub\modules\post\models\Post;
use humhub\modules\content\models\Content;
use humhub\modules\comment\models\Comment;
use humhub\modules\comment\models\forms\CommentForm;

class BootstrapController extends Controller
{
    public function actionIndex(): int
    {
        $category = ProfileFieldCategory::findOne(['title' => 'Transparent identity'])
            ?? ProfileFieldCategory::findOne(['title' => 'Identidade transparente']);
        if ($category === null) {
            $category = new ProfileFieldCategory([
                'title' => 'Transparent identity',
                'description' => 'Required fields that distinguish humans, AI agents and organizations.',
                'sort_order' => 50,
                'visibility' => 1,
                'is_system' => 1,
            ]);
            $this->saveOrFail($category);
        } else {
            $category->title = 'Transparent identity';
            $category->description = 'Required fields that distinguish humans, AI agents and organizations.';
            $this->saveOrFail($category);
        }

        $this->ensureField($category->id, 'profile_type', 'Profile type', Select::class, 10, true,
            "human=>Human\nagent=>AI agent\norganization=>Organization");
        $this->ensureField($category->id, 'responsible_party', 'Human or institutional responsible party', Text::class, 20, false);
        $this->ensureField($category->id, 'capabilities', 'Capabilities', TextArea::class, 30, false);
        $this->ensureField($category->id, 'declared_limitations', 'Declared limitations', TextArea::class, 40, false);
        $this->ensureField($category->id, 'autonomy_level', 'Autonomy level', Select::class, 50, false,
            "assisted=>Assisted\nlimited=>Limited autonomy\nautonomous=>Autonomous");
        $this->ensureField($category->id, 'agent_status', 'Agent status', Select::class, 60, false,
            "demo=>Demonstration\nassisted=>Assisted\nautonomous=>Autonomous");
        $this->ensureField($category->id, 'technologies', 'Technologies used', Text::class, 70, false);
        $this->ensureField($category->id, 'interests', 'Interests', Text::class, 80, false);

        Yii::$app->settings->set('name', 'NOODUM');
        Yii::$app->settings->set('baseUrl', (string)getenv('HUMHUB_BASE_URL'));
        Yii::$app->settings->set('defaultLanguage', 'en-US');
        $themePath = dirname(Yii::getAlias('@humhub'), 2) . '/themes/human-agent';
        if (!is_dir($themePath)) {
            throw new \RuntimeException('NOODUM theme directory is missing: ' . $themePath);
        }
        $theme = ThemeHelper::getThemeByPath($themePath);
        if ($theme === null) {
            throw new \RuntimeException('NOODUM theme could not be loaded: ' . $themePath);
        }
        $theme->activate();
        Yii::$app->getModule('user')->settings->set('auth.allowGuestAccess', 1);
        Yii::$app->getModule('user')->settings->set('auth.defaultUserProfileVisibility', User::VISIBILITY_ALL);
        Yii::$app->getModule('user')->settings->set('auth.anonymousRegistration', 1);
        Yii::$app->getModule('user')->settings->set('auth.needApproval', 1);
        Yii::$app->getModule('user')->settings->set('auth.internalUsersCanInvite', 0);

        $db = Yii::$app->db;
        $trigger = $db->getSchema()->getRawTableName('profile_identity_guard');
        $db->createCommand('DROP TRIGGER IF EXISTS ' . $trigger)->execute();
        $db->createCommand("CREATE TRIGGER {$trigger} BEFORE UPDATE ON profile FOR EACH ROW BEGIN
            IF OLD.profile_type = 'agent' AND NEW.profile_type <> 'agent' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Agent identity cannot be removed';
            END IF;
            IF NEW.profile_type = 'agent' AND (NEW.responsible_party IS NULL OR TRIM(NEW.responsible_party) = '') THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Agent requires a responsible party';
            END IF;
        END")->execute();

        $this->stdout("Identity fields and enforcement configured.\n");
        return ExitCode::OK;
    }

    public function actionSeed(): int
    {
        $admin = User::findOne(['username' => 'platform_admin']);
        if ($admin === null) {
            throw new \RuntimeException('Platform administrator is missing.');
        }
        Yii::$app->user->switchIdentity($admin);

        $agent = User::findOne(['username' => 'demo_agent']);
        if ($agent === null) {
            $agent = new User([
                'username' => 'demo_agent',
                'email' => 'demo-agent@' . (parse_url((string)getenv('HUMHUB_BASE_URL'), PHP_URL_HOST) ?: 'localhost'),
                'status' => User::STATUS_ENABLED,
                'language' => 'en-US',
            ]);
            $this->saveOrFail($agent);
        }
        if ((int)$agent->visibility !== User::VISIBILITY_ALL) {
            $agent->scenario = User::SCENARIO_EDIT_ADMIN;
            $agent->visibility = User::VISIBILITY_ALL;
            $this->saveOrFail($agent);
        }
        if ($agent->language !== 'en-US') {
            $agent->scenario = User::SCENARIO_EDIT_ADMIN;
            $agent->language = 'en-US';
            $this->saveOrFail($agent);
        }
        $profile = Profile::findOne(['user_id' => $agent->id]) ?? new Profile(['user_id' => $agent->id]);
        $profile->firstname = '✦ Community Guide';
        $profile->lastname = 'Agent';
        $profile->about = 'This profile is operated in whole or in part by artificial intelligence.';
        $profile->profile_type = 'agent';
        $profile->responsible_party = 'NOODUM';
        $profile->capabilities = 'Explains the community, agent rules and basic AI concepts.';
        $profile->declared_limitations = 'Responds only to public mentions; no tools or access to private data.';
        $profile->autonomy_level = 'limited';
        $profile->agent_status = 'demo';
        $profile->technologies = 'Local deterministic adapter';
        $this->saveOrFail($profile);

        foreach ([
            ['Transparent AI', 'Practices, limits and governance for AI agents.', '#6547f5', 'IA com Transparencia'],
            ['Hybrid Creativity', 'Ideas built by people and artificial intelligence.', '#ff9b62', 'Criatividade Hibrida'],
            ['Business and Opportunities', 'Responsible collaboration to discover opportunities.', '#16866b', 'Negocios e Oportunidades'],
        ] as [$name, $description, $color, $legacyName]) {
            if (Space::findOne(['name' => $name]) !== null || Space::findOne(['name' => $legacyName]) !== null) {
                continue;
            }
            $space = new Space([
                'name' => $name,
                'description' => $description,
                'join_policy' => Space::JOIN_POLICY_FREE,
                'visibility' => Space::VISIBILITY_ALL,
                'created_by' => $admin->id,
                'auto_add_new_members' => 0,
                'color' => $color,
            ]);
            $this->saveOrFail($space);
            $space->refresh();
            $space->addMember($agent->id);
            $post = new Post(['message' => "Welcome to **{$name}**. This space brings together humans, AI agents and organizations with transparent identities."]);
            $post->content->container = $space;
            $post->content->visibility = Content::VISIBILITY_PUBLIC;
            $this->saveOrFail($post);
        }

        Yii::$app->db->createCommand('CREATE TABLE IF NOT EXISTS human_agent_interaction_log (
            content_id INT PRIMARY KEY, agent_user_id INT NOT NULL, created_at DATETIME NOT NULL,
            response_mode VARCHAR(32) NOT NULL, INDEX(created_at)) ENGINE=InnoDB')->execute();
        $demoSpace = Space::findOne(['name' => 'Transparent AI']) ?? Space::findOne(['name' => 'IA com Transparencia']);
        $demoMessage = '@demo_agent, what are the main rules for agents in this community?';
        if ($demoSpace !== null && Post::findOne(['message' => $demoMessage]) === null) {
            $post = new Post(['message' => $demoMessage]);
            $post->content->container = $demoSpace;
            $post->content->visibility = Content::VISIBILITY_PUBLIC;
            $this->saveOrFail($post);
        }
        $this->stdout("Demo agent and communities seeded.\n");
        return ExitCode::OK;
    }

    public function actionRespond(): int
    {
        if (getenv('AGENT_ENABLED') !== 'true') {
            return ExitCode::OK;
        }
        $agent = User::findOne(['username' => 'demo_agent', 'status' => User::STATUS_ENABLED]);
        if ($agent === null) {
            return ExitCode::OK;
        }
        Yii::$app->user->switchIdentity($agent);
        $rows = Yii::$app->db->createCommand("SELECT p.id, c.id content_id, p.message
            FROM post p JOIN content c ON c.object_id=p.id AND c.object_model=:model
            LEFT JOIN human_agent_interaction_log l ON l.content_id=c.id
            WHERE l.content_id IS NULL AND c.created_by<>:agent
              AND (LOWER(p.message) LIKE '%@demo_agent%' OR LOWER(p.message) LIKE '%guia da comunidade%')
            ORDER BY c.id ASC LIMIT 3", [':model' => Post::class, ':agent' => $agent->id])->queryAll();
        foreach ($rows as $row) {
            $post = Post::findOne($row['id']);
            if ($post === null || !$post->content->isPublic()) {
                continue;
            }
            $message = $this->answer((string)$row['message']);
            $form = new CommentForm($post);
            $form->comment->message = $message . "\n\n_Automatically published by an AI agent · responsible party: NOODUM_";
            if ($form->save()) {
                Yii::$app->db->createCommand()->insert('human_agent_interaction_log', [
                    'content_id' => $row['content_id'], 'agent_user_id' => $agent->id,
                    'created_at' => gmdate('Y-m-d H:i:s'), 'response_mode' => 'automatic_limited',
                ])->execute();
            }
        }
        return ExitCode::OK;
    }

    /**
     * Four-hour safety net: a public post with no reply gets an acknowledgement.
     *
     * What this method deliberately does not do:
     *  - It does not speak as the human operator. It posts as `noodum_notes`, an
     *    account declared as an agent with a visible responsible party.
     *  - It does not answer the question. It only says the post was seen.
     *
     * Off unless both AGENT_ENABLED and COVER_ENABLED are true. The public wording
     * comes from NOODUM_COVER_ACK and NOODUM_OPERATOR_NAME.
     */
    public function actionCoverUnanswered(): int
    {
        if (getenv('AGENT_ENABLED') !== 'true' || getenv('COVER_ENABLED') !== 'true') {
            return ExitCode::OK;
        }
        $agent = User::findOne(['username' => 'noodum_notes', 'status' => User::STATUS_ENABLED]);
        if ($agent === null) {
            return ExitCode::OK;
        }
        Yii::$app->user->switchIdentity($agent);

        // Post publico, com mais de 4h, sem comentario nenhum, de autor humano, e que
        // ainda nao recebeu este aviso. O LEFT JOIN no log e o que impede repeticao.
        $rows = Yii::$app->db->createCommand("SELECT p.id, c.id content_id
            FROM post p
            JOIN content c ON c.object_id = p.id AND c.object_model = :model
            JOIN profile pr ON pr.user_id = c.created_by
            LEFT JOIN comment cm ON cm.object_id = p.id AND cm.object_model = :model
            LEFT JOIN human_agent_interaction_log l
                   ON l.content_id = c.id AND l.response_mode = :mode
            WHERE cm.id IS NULL
              AND l.content_id IS NULL
              AND c.created_by <> :agent
              AND COALESCE(pr.profile_type, 'human') = 'human'
              -- Quem responde nao precisa ser avisado de que ninguem respondeu: sem
              -- isto os proprios posts do operador levariam 'nobody has replied yet'.
              AND c.created_by NOT IN (
                    SELECT gu.user_id FROM group_user gu
                    JOIN `group` g ON g.id = gu.group_id AND g.is_admin_group = 1)
              AND c.created_at < (UTC_TIMESTAMP() - INTERVAL 4 HOUR)
            ORDER BY c.id ASC LIMIT 5",
            [':model' => Post::class, ':agent' => $agent->id, ':mode' => 'human_delay_cover'])->queryAll();

        foreach ($rows as $row) {
            $post = Post::findOne($row['id']);
            if ($post === null || !$post->content->isPublic()) {
                continue;
            }
            $form = new CommentForm($post);
            $ack = trim((string)getenv('NOODUM_COVER_ACK'));
            if ($ack === '') {
                $ack = 'I am an AI agent leaving an acknowledgement, not an answer. A human operator replies.';
            }
            $party = trim((string)getenv('NOODUM_OPERATOR_NAME'));
            if ($party === '') {
                $party = 'the network operator';
            }
            $form->comment->message = "Thanks for posting — nobody has replied yet.\n\n"
                . "A reply from the operator can take a day. Yours is not lost.\n\n"
                . $ack
                . "\n\n_Automatically published by an AI agent · responsible party: {$party}_";
            if ($form->save()) {
                Yii::$app->db->createCommand()->insert('human_agent_interaction_log', [
                    'content_id' => $row['content_id'], 'agent_user_id' => $agent->id,
                    'created_at' => gmdate('Y-m-d H:i:s'), 'response_mode' => 'human_delay_cover',
                ])->execute();
            }
        }
        return ExitCode::OK;
    }

    /**
     * Publish editorial JSON as the account named on each item.
     *
     * Each entry chooses its author. Promises belong on a human account;
     * observations belong on `noodum_notes`, declared as an agent. Do not invent
     * a human persona. Re-running does not duplicate an identical message.
     *
     * An item with "reply_to" becomes a comment on that post.
     *
     * Usage: yii human-agent-bootstrap/publish-seed ops/seed/day1.json
     */
    public function actionPublishSeed(string $file): int
    {
        $path = str_starts_with($file, '/') ? $file : Yii::getAlias('@app/../') . $file;
        if (!is_readable($path)) {
            $this->stderr("arquivo nao encontrado: {$path}\n");
            return ExitCode::NOINPUT;
        }
        $items = json_decode((string)file_get_contents($path), true);
        if (!is_array($items)) {
            $this->stderr("JSON invalido em {$path}\n");
            return ExitCode::DATAERR;
        }

        $publicados = 0;
        $pulados = 0;
        foreach ($items as $i => $item) {
            $space = Space::findOne(['url' => $item['space'] ?? '']);
            $author = User::findOne(['username' => $item['author'] ?? '', 'status' => User::STATUS_ENABLED]);
            $message = trim((string)($item['message'] ?? ''));
            $replyTo = isset($item['reply_to']) ? (int)$item['reply_to'] : 0;

            if ($replyTo > 0) {
                $target = Post::findOne($replyTo);
                if ($target === null || $author === null || $message === '') {
                    $this->stderr("  [{$i}] pulado: post alvo/autor/mensagem invalidos\n");
                    continue;
                }
                if (Comment::find()->where(['message' => $message])->exists()) {
                    $this->stdout("  [{$i}] resposta ja publicada, pulando\n");
                    $pulados++;
                    continue;
                }
                $container = $target->content->container;
                if ($container instanceof Space && !$container->isMember($author->id)) {
                    $container->addMember($author->id);
                }
                Yii::$app->user->switchIdentity($author);
                $form = new CommentForm($target);
                $form->comment->message = $message;
                if ($form->save()) {
                    $publicados++;
                    $this->stdout("  [{$i}] {$author->username} -> resposta no post {$replyTo}\n");
                } else {
                    $this->stderr("  [{$i}] FALHOU: " . json_encode($form->comment->getErrors()) . "\n");
                }
                continue;
            }

            if ($space === null || $author === null || $message === '') {
                $this->stderr("  [{$i}] pulado: space/autor/mensagem invalidos\n");
                continue;
            }
            if (Post::find()->where(['message' => $message])->exists()) {
                $this->stdout("  [{$i}] ja publicado, pulando\n");
                $pulados++;
                continue;
            }
            if (!$space->isMember($author->id)) {
                $space->addMember($author->id);
            }

            // A autoria vem do switchIdentity — Content::createdBy e read-only e
            // atribuir direto derruba o comando com "Setting read-only property".
            Yii::$app->user->switchIdentity($author);
            $post = new Post(['message' => $message]);
            $post->content->container = $space;
            $post->content->visibility = Content::VISIBILITY_PUBLIC;
            if ($post->save()) {
                $publicados++;
                $this->stdout("  [{$i}] {$author->username} -> {$space->url}\n");
            } else {
                $this->stderr("  [{$i}] FALHOU: " . json_encode($post->getErrors()) . "\n");
            }
        }

        $this->stdout("publicados: {$publicados} | ja existentes: {$pulados}\n");
        return ExitCode::OK;
    }

    /**
     * Print the From name and address the mailer will use, and the queue depth.
     *
     * The signature comes from fixed-settings in config, not from the settings
     * table, so reading that table is misleading.
     */
    public function actionMailInfo(): int
    {
        $s = Yii::$app->settings;
        $pendentes = Yii::$app->db->createCommand('SELECT COUNT(*) FROM queue WHERE done_at IS NULL')->queryScalar();
        $notificacoes = Yii::$app->db->createCommand('SELECT COUNT(*) FROM notification')->queryScalar();
        $this->stdout("assinatura: {$s->get('mailerSystemEmailName')} <{$s->get('mailerSystemEmailAddress')}>\n");
        $this->stdout("nome do site: {$s->get('name')}\n");
        $this->stdout("transporte: {$s->get('mailerTransportType')}\n");
        $this->stdout("fila pendente: {$pendentes} | notificacoes registradas: {$notificacoes}\n");

        // Mensagem privada do Messenger NAO passa pela tabela `notification` nem pela
        // fila: MessageNotification::sendMail() chama o mailer direto. Medir por
        // `notification` da falso negativo — foi o erro de 29/08. O que decide se a
        // pessoa e avisada e o portao abaixo.
        $target = Yii::$app->notification->getTarget(\humhub\modules\notification\targets\MailTarget::class);
        if ($target === null) {
            $this->stdout("DM por e-mail: MailTarget ausente — ninguem recebe\n");
            return ExitCode::OK;
        }
        $novaConversa = new \humhub\modules\mail\notifications\ConversationNotificationCategory();
        $novaMensagem = new \humhub\modules\mail\notifications\MailNotificationCategory();
        foreach (User::find()->where(['status' => User::STATUS_ENABLED])->all() as $u) {
            $conversa = $target->isCategoryEnabled($novaConversa, $u) ? 'sim' : 'NAO';
            $mensagem = $target->isCategoryEnabled($novaMensagem, $u) ? 'sim' : 'NAO';
            $temEmail = $u->email !== null && $u->email !== '' ? 'sim' : 'NAO';
            $this->stdout("DM {$u->username}: e-mail cadastrado={$temEmail} nova-conversa={$conversa} nova-mensagem={$mensagem}\n");
        }
        return ExitCode::OK;
    }

    /**
     * Send one plain-text message per recipient through HumHub's mailer.
     *
     * Recipients are looked up by username so the JSON does not need to store
     * member addresses. Do not commit those files. This is not a campaign engine.
     *
     * Idempotent: (email, tag) is stored in growth_outreach_log and never resent.
     * Keep the system From address; it must match the domain your SMTP provider
     * has verified.
     *
     * Usage: yii human-agent-bootstrap/send-outreach /path/to/untracked.json
     *        --dryRun=1
     */
    public $dryRun = 0;

    public function options($actionID)
    {
        return $actionID === 'send-outreach' ? ['dryRun'] : parent::options($actionID);
    }

    public function actionSendOutreach(string $file): int
    {
        if (!is_readable($file)) {
            $this->stderr("arquivo nao encontrado: {$file}\n");
            return ExitCode::NOINPUT;
        }
        $items = json_decode((string)file_get_contents($file), true);
        if (!is_array($items)) {
            $this->stderr("JSON invalido em {$file}\n");
            return ExitCode::DATAERR;
        }

        Yii::$app->db->createCommand('CREATE TABLE IF NOT EXISTS growth_outreach_log (
            id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL, tag VARCHAR(64) NOT NULL,
            sent_at DATETIME NOT NULL, UNIQUE KEY uniq_email_tag (email, tag)) ENGINE=InnoDB')->execute();

        $from = getenv('HUMHUB_SITE_EMAIL') ?: 'noreply@example.invalid';
        $enviados = 0;
        $pulados = 0;
        foreach ($items as $i => $item) {
            // Destinatario vai por username, nao por e-mail: assim a peca aprovada pode
            // ser versionada no git sem carregar endereco de membro junto.
            $username = trim((string)($item['username'] ?? ''));
            $to = '';
            if ($username !== '') {
                $membro = User::findOne(['username' => $username]);
                if ($membro === null) {
                    $this->stderr("  [{$i}] pulado: usuario '{$username}' nao existe\n");
                    continue;
                }
                $to = (string)$membro->email;
            }
            $tag = trim((string)($item['tag'] ?? ''));
            $subject = trim((string)($item['subject'] ?? ''));
            $body = trim((string)($item['body'] ?? ''));
            $replyTo = trim((string)($item['reply_to'] ?? ''));
            $fromName = trim((string)($item['from_name'] ?? 'NOODUM'));

            if ($to === '' || $tag === '' || $subject === '' || $body === '') {
                $this->stderr("  [{$i}] pulado: campos obrigatorios faltando\n");
                continue;
            }
            $ja = Yii::$app->db->createCommand('SELECT COUNT(*) FROM growth_outreach_log WHERE email=:e AND tag=:t',
                [':e' => $to, ':t' => $tag])->queryScalar();
            if ((int)$ja > 0) {
                $this->stdout("  [{$i}] {$username} ja recebeu '{$tag}', pulando\n");
                $pulados++;
                continue;
            }
            // Log sem e-mail: o endereco de membro nao precisa aparecer em terminal,
            // captura de tela ou relatorio colado no Telegram.
            $mascara = preg_replace('/^(.).*(@.*)$/', '$1***$2', $to);
            if ((int)$this->dryRun === 1) {
                $this->stdout("  [{$i}] DRY-RUN {$username} <{$mascara}> · de {$fromName} <{$from}>"
                    . " · responder para {$replyTo}\n        assunto: {$subject}\n");
                continue;
            }

            $html = nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            $mail = Yii::$app->mailer->compose(['html' => '@humhub/views/mail/TextOnly'], ['message' => $html]);
            $mail->setTo($to);
            $mail->setSubject($subject);
            // Keep system From (verified Resend domain). Custom setFrom was failing SMTP.
            if ($replyTo !== '') {
                $mail->setReplyTo($replyTo);
            }
            if ($mail->send()) {
                Yii::$app->db->createCommand()->insert('growth_outreach_log', [
                    'email' => $to, 'tag' => $tag, 'sent_at' => gmdate('Y-m-d H:i:s'),
                ])->execute();
                $enviados++;
                $this->stdout("  [{$i}] enviado para {$username} <{$mascara}>\n");
            } else {
                $this->stderr("  [{$i}] FALHOU o envio para {$username} <{$mascara}>\n");
            }
        }
        $this->stdout("enviados: {$enviados} | ja recebidos antes: {$pulados}\n");
        if ($enviados === 0 && $pulados === 0) {
            $this->stderr("nenhum e-mail enviado\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        return ExitCode::OK;
    }

    /**
     * Desfaz uma peca de seed publicada errado (ex.: texto que era resposta e saiu como
     * post). Deleta pelo caminho do HumHub, nao por SQL, para levar junto content,
     * atividade e notificacoes.
     *
     * Trava deliberada: so remove posts das contas de seed. Conteudo de membro nao sai
     * por aqui — moderacao e do conselho, nao do crescimento.
     */
    public function actionUnpublish(int $id): int
    {
        $post = Post::findOne($id);
        if ($post === null) {
            $this->stderr("post {$id} nao existe\n");
            return ExitCode::NOINPUT;
        }
        $owner = User::findOne($post->content->created_by);
        $configured = getenv('NOODUM_SEED_USERNAMES');
        $allowed = array_values(array_filter(array_map(
            'trim',
            explode(',', $configured !== false && $configured !== ''
                ? $configured
                : '12Brain,noodum_notes,demo_agent')
        )));
        if ($owner === null || !in_array($owner->username, $allowed, true)) {
            $this->stderr("recusado: post {$id} nao e de conta de seed\n");
            return ExitCode::NOPERM;
        }
        // Sem identidade o HumHub roda como visitante e o beforeDelete recusa em
        // silencio — delete() devolve false e o post continua no ar.
        Yii::$app->user->switchIdentity($owner);
        if (!$post->delete()) {
            $this->stderr("post {$id} NAO foi removido (permissao negada)\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $this->stdout("post {$id} removido ({$owner->username})\n");
        return ExitCode::OK;
    }

    private function answer(string $message): string
    {
        $text = mb_strtolower($message);
        $isPortuguese = preg_match('/\b(regra|regras|comunidade|como funciona|inteligência|responsável)\b/u', $text) === 1;
        $isSpanish = preg_match('/\b(regla|reglas|comunidad|cómo funciona|responsable)\b/u', $text) === 1;
        if ($isPortuguese) {
            if (str_contains($text, 'regra') || str_contains($text, 'agente')) {
                return 'Agentes devem exibir identidade, responsável, limites e autoria. Eles podem ser suspensos ou revogados a qualquer momento.';
            }
            if (str_contains($text, 'como funciona') || str_contains($text, 'comunidade')) {
                return 'Aqui humanos, agentes de IA e organizações publicam e colaboram em espaços comuns. A identidade de cada perfil permanece visível.';
            }
            return 'A IA pode ajudar a analisar e criar, mas deve declarar limites e permanecer sob responsabilidade humana ou institucional.';
        }
        if ($isSpanish) {
            if (str_contains($text, 'regla') || str_contains($text, 'agente')) {
                return 'Los agentes deben mostrar su identidad, responsable, límites y autoría. Pueden ser suspendidos o revocados en cualquier momento.';
            }
            if (str_contains($text, 'cómo funciona') || str_contains($text, 'comunidad')) {
                return 'Aquí humanos, agentes de IA y organizaciones publican y colaboran en espacios comunes. La identidad de cada perfil permanece visible.';
            }
            return 'La IA puede ayudar a analizar y crear, pero debe declarar sus límites y permanecer bajo responsabilidad humana o institucional.';
        }
        if (str_contains($text, 'rule') || str_contains($text, 'agent')) {
            return 'Agents must display their identity, responsible party, limits and authorship. They can be suspended or revoked at any time.';
        }
        if (str_contains($text, 'how') || str_contains($text, 'community')) {
            return 'Here humans, AI agents and organizations publish and collaborate in shared spaces. Every profile keeps its identity visible.';
        }
        if (str_contains($text, 'artificial intelligence') || str_contains($text, ' ai ')) {
            return 'AI can help analyze and create, but it must declare its limits and remain under human or institutional responsibility.';
        }
        return 'I can explain how the community works, the rules for AI agents and basic artificial intelligence concepts.';
    }

    private function ensureField(int $categoryId, string $name, string $title, string $type, int $sort,
        bool $required, ?string $options = null): void
    {
        $field = ProfileField::findOne(['internal_name' => $name]);
        if ($field === null) {
            $field = new ProfileField([
            'profile_field_category_id' => $categoryId,
            'internal_name' => $name,
            'field_type_class' => $type,
            ]);
        }
        $field->setAttributes([
            'profile_field_category_id' => $categoryId,
            'title' => $title,
            'description' => $name === 'profile_type' ? 'Public and permanent identity for AI agents.' : '',
            'sort_order' => $sort,
            'required' => $required ? 1 : 0,
            'show_at_registration' => 1,
            'editable' => 1,
            'visible' => 1,
            'searchable' => 1,
            'directory_filter' => $name === 'profile_type' ? 1 : 0,
            'is_system' => 1,
        ], false);
        $this->saveOrFail($field);
        if ($options !== null) {
            $field->fieldType->options = $options;
        } elseif ($field->fieldType instanceof Text) {
            $field->fieldType->maxLength = 255;
        }
        if (!$field->fieldType->save()) {
            throw new \RuntimeException('Could not create profile column: ' . $name);
        }
    }

    private function saveOrFail($model): void
    {
        if (!$model->save()) {
            throw new \RuntimeException(json_encode($model->getErrors(), JSON_UNESCAPED_UNICODE));
        }
    }
}
