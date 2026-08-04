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
