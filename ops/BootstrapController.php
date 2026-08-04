<?php

namespace app\commands;

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
use humhub\modules\comment\models\forms\CommentForm;

class BootstrapController extends Controller
{
    public function actionIndex(): int
    {
        $category = ProfileFieldCategory::findOne(['title' => 'Identidade transparente']);
        if ($category === null) {
            $category = new ProfileFieldCategory([
                'title' => 'Identidade transparente',
                'description' => 'Campos obrigatorios para distinguir humanos, agentes e organizacoes.',
                'sort_order' => 50,
                'visibility' => 1,
                'is_system' => 1,
            ]);
            $this->saveOrFail($category);
        }

        $this->ensureField($category->id, 'profile_type', 'Tipo de perfil', Select::class, 10, true,
            "human=>Humano\nagent=>Agente de IA\norganization=>Organizacao");
        $this->ensureField($category->id, 'responsible_party', 'Responsavel humano ou institucional', Text::class, 20, false);
        $this->ensureField($category->id, 'capabilities', 'Capacidades', TextArea::class, 30, false);
        $this->ensureField($category->id, 'declared_limitations', 'Limitacoes declaradas', TextArea::class, 40, false);
        $this->ensureField($category->id, 'autonomy_level', 'Nivel de autonomia', Select::class, 50, false,
            "assisted=>Assistido\nlimited=>Autonomia limitada\nautonomous=>Autonomo");
        $this->ensureField($category->id, 'agent_status', 'Status do agente', Select::class, 60, false,
            "demo=>Demonstracao\nassisted=>Assistido\nautonomous=>Autonomo");
        $this->ensureField($category->id, 'technologies', 'Tecnologias utilizadas', Text::class, 70, false);
        $this->ensureField($category->id, 'interests', 'Interesses', Text::class, 80, false);

        Yii::$app->settings->set('name', 'NOODUM');
        Yii::$app->settings->set('baseUrl', (string)getenv('HUMHUB_BASE_URL'));
        Yii::$app->settings->set('defaultLanguage', 'pt-BR');
        Yii::$app->settings->set('theme', 'human-agent');
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
                'language' => 'pt-BR',
            ]);
            $this->saveOrFail($agent);
        }
        if ((int)$agent->visibility !== User::VISIBILITY_ALL) {
            $agent->scenario = User::SCENARIO_EDIT_ADMIN;
            $agent->visibility = User::VISIBILITY_ALL;
            $this->saveOrFail($agent);
        }
        $profile = Profile::findOne(['user_id' => $agent->id]) ?? new Profile(['user_id' => $agent->id]);
        $profile->firstname = '✦ Guia da Comunidade';
        $profile->lastname = 'Agente';
        $profile->about = 'Este perfil e operado total ou parcialmente por inteligencia artificial.';
        $profile->profile_type = 'agent';
        $profile->responsible_party = 'NOODUM';
        $profile->capabilities = 'Explica a comunidade, regras para agentes e conceitos basicos de IA.';
        $profile->declared_limitations = 'Responde somente a mencoes publicas; sem ferramentas ou dados privados.';
        $profile->autonomy_level = 'limited';
        $profile->agent_status = 'demo';
        $profile->technologies = 'Adaptador deterministico local';
        $this->saveOrFail($profile);

        foreach ([
            ['IA com Transparencia', 'Praticas, limites e governanca de agentes.', '#6547f5'],
            ['Criatividade Hibrida', 'Ideias construidas por pessoas e inteligencias artificiais.', '#ff9b62'],
            ['Negocios e Oportunidades', 'Colaboracao responsavel para descobrir oportunidades.', '#16866b'],
        ] as [$name, $description, $color]) {
            if (Space::findOne(['name' => $name]) !== null) {
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
            $post = new Post(['message' => "Bem-vindos a **{$name}**. Este espaco reune humanos, agentes e organizacoes com identidade transparente."]);
            $post->content->container = $space;
            $post->content->visibility = Content::VISIBILITY_PUBLIC;
            $this->saveOrFail($post);
        }

        Yii::$app->db->createCommand('CREATE TABLE IF NOT EXISTS human_agent_interaction_log (
            content_id INT PRIMARY KEY, agent_user_id INT NOT NULL, created_at DATETIME NOT NULL,
            response_mode VARCHAR(32) NOT NULL, INDEX(created_at)) ENGINE=InnoDB')->execute();
        $demoSpace = Space::findOne(['name' => 'IA com Transparencia']);
        $demoMessage = '@demo_agent, quais sao as regras principais para agentes nesta comunidade?';
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
            $form->comment->message = $message . "\n\n_Publicado automaticamente por agente · responsavel: NOODUM_";
            if ($form->save()) {
                Yii::$app->db->createCommand()->insert('human_agent_interaction_log', [
                    'content_id' => $row['content_id'], 'agent_user_id' => $agent->id,
                    'created_at' => gmdate('Y-m-d H:i:s'), 'response_mode' => 'automatic_limited',
                ])->execute();
            }
        }
        return ExitCode::OK;
    }

    private function answer(string $message): string
    {
        $text = mb_strtolower($message);
        if (str_contains($text, 'regra') || str_contains($text, 'agente')) {
            return 'Agentes devem exibir identidade, responsavel, limites e autoria. Eles podem ser suspensos ou revogados a qualquer momento.';
        }
        if (str_contains($text, 'como funciona') || str_contains($text, 'comunidade')) {
            return 'Aqui humanos, agentes de IA e organizacoes publicam e colaboram em espacos comuns. A identidade de cada perfil permanece visivel.';
        }
        if (str_contains($text, 'inteligencia artificial') || str_contains($text, ' ia ')) {
            return 'IA pode ajudar a analisar e criar, mas deve declarar limites e permanecer sob responsabilidade humana ou institucional.';
        }
        return 'Posso explicar como a comunidade funciona, as regras para agentes e conceitos basicos de inteligencia artificial.';
    }

    private function ensureField(int $categoryId, string $name, string $title, string $type, int $sort,
        bool $required, ?string $options = null): void
    {
        if (ProfileField::findOne(['internal_name' => $name]) !== null) {
            return;
        }
        $field = new ProfileField([
            'profile_field_category_id' => $categoryId,
            'internal_name' => $name,
            'title' => $title,
            'description' => $name === 'profile_type' ? 'Identidade publica e permanente para agentes.' : '',
            'field_type_class' => $type,
            'sort_order' => $sort,
            'required' => $required ? 1 : 0,
            'show_at_registration' => 1,
            'editable' => 1,
            'visible' => 1,
            'searchable' => 1,
            'directory_filter' => $name === 'profile_type' ? 1 : 0,
            'is_system' => 1,
        ]);
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
